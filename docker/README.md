### Quick Start

```bash
# 1. Install dependencies
npm install

# 2. Start WordPress container and set up the site (auto-starts infrastructure if needed)
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

This will:
1. Auto-start shared infrastructure (database, phpMyAdmin) if not already running
2. Create/recreate the WordPress container (uses port 8082 and container ID "default" if `.env` doesn't exist)
3. Run the setup script to install WordPress, WooCommerce, and WooPayments

**Note:** The shared infrastructure (database and phpMyAdmin) is started automatically from your main checkout when needed. If you're in a worktree, the infrastructure will be started from the main checkout directory. You can also start it manually with `npm run infra:up` if you prefer explicit control.

**Note:** For custom port/container configuration, copy `.env.example` to `.env` and edit it, or run `npm run worktree:setup` to auto-generate one.

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

**Note:** If `.env` doesn't exist, Docker Compose uses defaults (port 8082, container ID "default"). Copy `.env.example` to `.env` and customize, or run `npm run worktree:setup` to auto-generate one.

### WordPress Admin

Open http://localhost:<YOUR_PORT>/wp-admin/ (check `.env` for your port; default is 8082 for main checkout, worktrees get auto-assigned ports from 8180-8199)
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

#### Listing all worktrees

To see all worktrees and their Docker status:

```bash
npm run worktree:status
```

This shows:
- Port and URL for each worktree
- Docker container status (running/stopped/no container)
- Current worktree marked with `*`
- Warnings for orphan containers

#### Creating a new worktree

```bash
# Create the worktree
git worktree add ../my-feature-branch feature-branch

# Navigate to it
cd ../my-feature-branch

# Install dependencies
npm install

# Configure port and worktree ID (creates .env file)
npm run worktree:setup

# Start WordPress
npm run up:recreate
```

The `worktree:setup` command scans for an available port (8180-8199), derives a `WORKTREE_ID` from the directory name, and creates a `.env` file with both values.

#### Removing a worktree

Before removing a worktree, clean up its Docker resources:

```bash
cd /path/to/worktree
npm run worktree:cleanup
cd ..
git worktree remove /path/to/worktree
```

This will:
- Stop the worktree's WordPress container
- Drop the worktree's test database (`wcpay_tests_<WORKTREE_ID>`) from the shared DB
- Remove the `.env` file

#### Customizing your worktree config

Edit `.env` to customize:
```bash
# Port for this worktree's WordPress instance
WORDPRESS_PORT=8086

# Unique identifier (used in container names)
WORKTREE_ID=my_feature
```

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
| Plugins (WooCommerce, etc.) | Shared | `./docker/wordpress/wp-content/plugins` |
| Themes | Shared | `./docker/wordpress/wp-content/themes` |
| Uploads (media) | Shared | `./docker/wordpress/wp-content/uploads` |
| mu-plugins | Shared | `./docker/wordpress/wp-content/mu-plugins` |
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

Files are stored directly on your host filesystem:
```bash
# List plugins
ls docker/wordpress/wp-content/plugins

# List themes
ls docker/wordpress/wp-content/themes
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

### IDE setup for xDebug

Add the following path mappings to your IDE so it can find the correct code when debugging:

* `<project folder>/` → `/var/www/html/wp-content/plugins/woocommerce-payments`
* `<project folder>/docker/wordpress` → `/var/www/html`

For WordPress core function hinting, add `docker/wordpress` to your IDE's PHP include path.

**Note:** Plugins (like WooCommerce) are stored in shared Docker volumes, not locally. For plugin hinting, you can copy files locally:
```bash
# Get your container name from .env (WORKTREE_ID) or use 'default' for main checkout
docker cp wcpay_wp_<worktree_id>:/var/www/html/wp-content/plugins/woocommerce ./docker/wordpress/wp-content/plugins/
```

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

You can add PHP scripts to the `mu-plugins` directory (`docker/wordpress/wp-content/mu-plugins`). These are treated as [WordPress must-use plugins](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) and loaded automatically.

**Note:** Since mu-plugins are shared across all worktrees, any script you add will affect all environments.

**Adding a mu-plugin:**

```bash
# Create the file directly in the mu-plugins directory
echo '<?php // My helper script' > docker/wordpress/wp-content/mu-plugins/my-helper.php
```

**Editing an existing mu-plugin:**

```bash
# Edit the file directly
vim docker/wordpress/wp-content/mu-plugins/my-helper.php
```

**Listing mu-plugins:**

```bash
ls -la docker/wordpress/wp-content/mu-plugins/
```

**Removing a mu-plugin:**

```bash
rm docker/wordpress/wp-content/mu-plugins/my-helper.php
```
