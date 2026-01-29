### Quick Start

```bash
# 1. Install dependencies
npm install

# 2. Start WordPress container and set up the site
npm run up:recreate

# 3. Build JS assets (or use `npm start` to watch for changes)
npm run build:client
```

After these steps, your site will be available at `http://localhost:<PORT>/wp-admin/` (check `.env` for your port).

Login credentials: `admin` / `admin`

---

### Setting up the Docker environment

#### Step 1: Install dependencies

```bash
npm install
```

#### Step 2: Start WordPress and set up the site

For first-time setup (creates container AND configures WordPress/WooPayments):

```bash
npm run up:recreate
```

This automatically starts the shared infrastructure (database, phpMyAdmin) if not already running, then:
1. Creates/recreates the WordPress container
2. Runs the setup script to install WordPress, WooCommerce, and WooPayments

This will:
1. Create/recreate the WordPress container (uses port 8082 and container ID "default" if `.env` doesn't exist)
2. Run the setup script to install WordPress, WooCommerce, and WooPayments

**Note:** For custom port/container configuration, copy `.env.example` to `.env` and edit it. For git worktrees, use `npm run worktree:create` instead (see [Working with Git Worktrees](#working-with-git-worktrees)).

For subsequent startups (container already configured):

```bash
npm run up
```

#### Step 3: Build JS assets

Build once for production:

```bash
npm run build:client
```

Or watch for changes during development:

```bash
npm start
```

You can also combine container startup with watch mode:

```bash
npm run dev
```

#### Checking your port

Your WordPress port is saved in `.env`. Check it with:

```
cat .env
```

Or it's displayed when you run `npm run up`.

**Note:** If `.env` doesn't exist, Docker Compose uses defaults (port 8082, container ID "default"). For the main checkout, copy `.env.example` to `.env` and customize. For worktrees, use `npm run worktree:create` instead.

### WordPress Admin

Open http://localhost:<YOUR_PORT>/wp-admin/ (check `.env` for your port; default is 8082 for main checkout, worktrees get auto-assigned ports from 8084-8099)
```
Username: admin
Password: admin
```

### Connecting to MySQL

Open phpMyAdmin at http://localhost:8083/, or connect using other MySQL clients with these credentials:
```
Host: localhost
Port: 5678
Username: wordpress
Password: wordpress
```

### Working with Git Worktrees

The Docker setup is designed to work seamlessly with git worktrees. Each worktree gets its own WordPress container with a unique port.

#### Creating a Worktree

Create a fully configured worktree with a single command:

```bash
npm run worktree:create my-feature develop
```

This handles everything automatically:
1. Creates the git worktree from the specified branch (default: `develop`)
2. Installs npm and composer dependencies
3. Assigns an available port (8084-8099)
4. Starts the Docker container
5. Sets up WordPress, WooCommerce, and WooPayments
6. Runs a health check to verify the site is accessible
7. Creates a `.worktree-info.json` file with connection details

Example output:
```
Creating worktree 'my-feature' from 'develop'...
  [1/7] Checking infrastructure... done
  [2/7] Creating git worktree... done
  [3/7] Installing npm dependencies... done
  [4/7] Installing composer dependencies... done
  [5/7] Configuring port (8086)... done
  [6/7] Starting Docker container... done
  [7/7] Setting up WordPress... done

SUCCESS! Worktree is ready.

  URL:       http://localhost:8086
  Admin:     http://localhost:8086/wp-admin/
  Login:     admin / admin
  Path:      /Users/you/projects/my-feature
```

#### Checking Worktree Status

View the status of all worktrees and their containers:

```bash
npm run worktree:status
```

Example output:
```
Worktree Status
===============
  NAME              PORT   URL                      STATUS
  wcpay (main)      8082   http://localhost:8082    running
  my-feature        8086   http://localhost:8086    running

Warnings:
  - Orphan container: wcpay_wp_old (no matching worktree)
```

For machine-readable output (useful for scripts and agents):
```bash
npm run worktree:status -- --json
```

#### Removing a Worktree

Remove a worktree and all its Docker resources with:

```bash
npm run worktree:remove my-feature
```

This will:
- Stop and remove the Docker container
- Drop the test database (`wcpay_tests_<WORKTREE_ID>`)
- Remove `.env` and `.worktree-info.json` files
- Remove the git worktree

Use `--force` if there are uncommitted changes:
```bash
npm run worktree:remove my-feature -- --force
```

#### Worktree Info File

Each worktree generates a `.worktree-info.json` file with connection details:

```json
{
  "version": 1,
  "worktree_id": "my_feature",
  "port": 8086,
  "url": "http://localhost:8086",
  "admin_url": "http://localhost:8086/wp-admin/",
  "container_name": "wcpay_wp_my_feature",
  "created_at": "2026-01-29T10:30:00Z",
  "base_branch": "develop",
  "path": "/Users/you/projects/my-feature"
}
```

This file is useful for:
- Scripts and automation tools
- Claude agents working on multiple worktrees
- Quick reference for connection details

### Stopping the environment

```bash
# Stop this worktree's WordPress container
npm run down

# Stop all shared infrastructure (DB, phpMyAdmin)
npm run infra:down
```

### Shared vs Per-Worktree Resources

The Docker setup is designed for multiple worktrees to share a single database while each testing their own WooPayments code.

| Resource | Shared/Per-Worktree | Location |
|----------|---------------------|----------|
| Database (MySQL) | Shared | `wcpay_db` container |
| Plugins (WooCommerce, etc.) | Shared | `wcpay-plugins` Docker volume |
| Themes | Shared | `wcpay-themes` Docker volume |
| Uploads (media) | Shared | `wcpay-uploads` Docker volume |
| mu-plugins | Shared | `wcpay-mu-plugins` Docker volume |
| **WooPayments plugin code** | **Per-worktree** | Bind mount from repo root |
| WordPress container | Per-worktree | `wcpay_wp_<WORKTREE_ID>` |
| WooCommerce logs | Per-worktree | `./docker/logs/wc-logs` |
| Apache logs | Per-worktree | `./docker/logs/apache2` |

**Why this design?**
- Installing a plugin or theme in one worktree makes it available to all (matches the shared DB state)
- Each worktree tests its own WooPayments code changes in isolation
- Logs (WooCommerce and Apache) stay separate per worktree for easier debugging

> [!WARNING]
> Shared database means shared state. If you're testing destructive operations (database migrations, data deletions, etc.), changes will affect all your running worktrees. Consider backing up the database first or testing destructive changes in isolation.

**To browse shared plugin/theme files:**
```bash
# List plugins in the shared volume
docker exec wcpay_wp_default ls /var/www/html/wp-content/plugins

# Copy a file out for inspection
docker cp wcpay_wp_default:/var/www/html/wp-content/plugins/woocommerce/woocommerce.php ./
```

### Exposing Your Local Site (for Jetpack Connection)

To connect WooPayments to Stripe or use Jetpack features, your local site needs to be accessible from the internet. Two options are available:

#### Option 1: Jurassic Tube (recommended for A8C employees)

Jurassic Tube is a tunneling service for a12s.

**First-time setup:**

```bash
npm run tube:setup
```

This will:
1. Download and install the Jurassic Tube client
2. Generate SSH keys and guide you to register them at https://jurassic.tube/
3. Prompt you to create a subdomain
4. Save your configuration to `bin/jurassictube/config.env`

**Starting the tunnel:**

```bash
npm run tube:start
```

**Stopping the tunnel:**

```bash
npm run tube:stop
```

Your site will be available at `https://<your-subdomain>.jurassic.tube/`

#### Option 2: Ngrok

You don't need a paid plan for this.

In a new terminal window run (replace PORT with your actual port from `.env`):

```bash
ngrok http <PORT>
```

You will see it give a forwarding address like this one:
 http://e0747cffd8a3.ngrok.io

You may need to temporarily set your `siteurl` and `home` `wp_option`s to the new url. You can do this with phpMyAdmin or WP-CLI.

Visit the `<url>`, login and setup WCPay.

### Changing default port for xDebug

To change the default port for xDebug you should create `docker-compose.override.yml` with the following contents:
```
services:
  wordpress:
    build:
      args:
        - XDEBUG_REMOTE_PORT=9003 # IDE/Editor's listener port
```
I used port `9003` as an example.
To apply the change, restart your containers using `npm run down && npm run up`

### Mapping WooCommerce development repo plugin folder

If you also work on [WooCommerce core](https://github.com/woocommerce/woocommerce) that you want to use in your Docker environment, you can map it by adding a volume mapping to `docker-compose.override.yml`.

For example: if your WooCommerce core repo path is `/path/to/your/repo/woocommerce`, you should append `plugins/woocommerce` to this path and configure it like this.

```
services:
  wordpress:
    volumes:
      - /path/to/your/repo/woocommerce/plugins/woocommerce:/var/www/html/wp-content/plugins/woocommerce
```

To apply the change, restart your containers using `npm run down && npm run up`. In case, it's not working properly yet, ensure that you follow the WooCommerce code README.md and build the plugin there.

### Adding local helper scripts/hacks

You can add PHP scripts to the `mu-plugins` directory (stored in the shared `wcpay-mu-plugins` Docker volume). These are treated as [WordPress must-use plugins](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) and loaded automatically.

**Note:** Since mu-plugins are shared across all worktrees, any script you add will affect all environments.

**Adding a mu-plugin:**

```bash
# Create a local file
echo '<?php // My helper script' > my-helper.php

# Copy it into the shared volume (use any running WordPress container)
docker cp my-helper.php wcpay_wp_default:/var/www/html/wp-content/mu-plugins/

# Clean up local file
rm my-helper.php
```

**Editing an existing mu-plugin:**

```bash
# Copy out, edit, copy back
docker cp wcpay_wp_default:/var/www/html/wp-content/mu-plugins/my-helper.php ./
# ... edit the file ...
docker cp my-helper.php wcpay_wp_default:/var/www/html/wp-content/mu-plugins/
```

**Listing mu-plugins:**

```bash
docker exec wcpay_wp_default ls -la /var/www/html/wp-content/mu-plugins/
```

**Removing a mu-plugin:**

```bash
docker exec wcpay_wp_default rm /var/www/html/wp-content/mu-plugins/my-helper.php
```
