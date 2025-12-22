#!/usr/bin/env php
<?php
/**
 * Migration script to convert Psalm annotations to PHPStan annotations
 * 
 * This script helps migrate from Psalm to PHPStan by converting common annotations.
 * Run it with: php bin/migrate-psalm-to-phpstan.php [--dry-run] [--verbose]
 */

class PsalmToPHPStanMigrator
{
    private $dryRun = false;
    private $verbose = false;
    private $filesProcessed = 0;
    private $changesMade = 0;
    private $filesWithChanges = 0;
    
    private $annotationMap = [
        '/@psalm-suppress (.*?)\n/' => '@phpstan-ignore-line // $1
',
        '/@psalm-param (.*?)\n/' => '@param $1
',
        '/@psalm-immutable\n/' => '@phpstan-immutable
',
        '/\* @psalm-suppress/' => '* @phpstan-ignore-line',
    ];
    
    public function __construct()
    {
        $this->parseArguments();
    }
    
    private function parseArguments(): void
    {
        global $argv;
        
        foreach ($argv as $arg) {
            if ($arg === '--dry-run') {
                $this->dryRun = true;
                echo "Running in dry-run mode (no changes will be made)\n";
            }
            if ($arg === '--verbose') {
                $this->verbose = true;
            }
        }
    }
    
    public function run(): void
    {
        echo "Starting Psalm to PHPStan annotation migration...\n";
        echo "Dry run: " . ($this->dryRun ? "Yes" : "No") . "\n";
        echo "Verbose: " . ($this->verbose ? "Yes" : "No") . "\n\n";
        
        $directories = ['includes', 'src'];
        
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                echo "Directory $directory not found, skipping...\n";
                continue;
            }
            
            $this->processDirectory($directory);
        }
        
        $this->printSummary();
    }
    
    private function processDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->processFile($file->getPathname());
            }
        }
    }
    
    private function processFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            echo "Could not read file: $filePath\n";
            return;
        }
        
        $originalContent = $content;
        $hasPsalmAnnotations = strpos($content, '@psalm') !== false;
        
        if (!$hasPsalmAnnotations) {
            if ($this->verbose) {
                echo "No Psalm annotations found in: $filePath\n";
            }
            return;
        }
        
        $this->filesProcessed++;
        
        if ($this->verbose) {
            echo "Processing file with Psalm annotations: $filePath\n";
        }
        
        // Apply all annotation conversions
        foreach ($this->annotationMap as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // Check if content changed
        if ($content !== $originalContent) {
            $this->changesMade++;
            
            if (!$this->dryRun) {
                $result = file_put_contents($filePath, $content);
                if ($result !== false) {
                    $this->filesWithChanges++;
                    if ($this->verbose) {
                        echo "  ✓ Updated: $filePath\n";
                    }
                } else {
                    echo "  ✗ Failed to write: $filePath\n";
                }
            } else {
                if ($this->verbose) {
                    echo "  → Would update: $filePath\n";
                }
            }
        }
    }
    
    private function printSummary(): void
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "MIGRATION SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "Files processed: " . $this->filesProcessed . "\n";
        echo "Files with Psalm annotations: " . $this->changesMade . "\n";
        
        if ($this->dryRun) {
            echo "Files that would be updated: " . $this->changesMade . "\n";
        } else {
            echo "Files updated: " . $this->filesWithChanges . "\n";
        }
        
        echo "\nMigration complete!\n";
        
        if ($this->dryRun) {
            echo "\nTo apply these changes, run without --dry-run flag:\n";
            echo "php bin/migrate-psalm-to-phpstan.php\n";
        }
    }
}

// Run the migrator
try {
    $migrator = new PsalmToPHPStanMigrator();
    $migrator->run();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}