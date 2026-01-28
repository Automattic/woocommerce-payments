### Quick Start

```bash
# 1. Install dependencies
npm install

# 2. Start shared infrastructure (database & phpMyAdmin) - first time only
npm run infra:up

# 3. Start WordPress container and set up the site
npm run up:recreate

# 4. Build JS assets (or use `npm start` to watch for changes)
npm run build:client
```

After these steps, your site will be available at `http://localhost:<PORT>/wp-admin/` (check `.env.local` for your port).

Login credentials: `admin` / `admin`

---

### Setting up the Docker environment

#### Step 1: Install dependencies

```bash
npm install
```

#### Step 2: Start shared infrastructure (first time only)

The database and phpMyAdmin are shared across all worktrees. Start them once from your main checkout:

```bash
npm run infra:up
```

This creates a shared Docker network (`wcpay-network`) that all WordPress containers will join.

#### Step 3: Start WordPress and set up the site

For first-time setup (creates container AND configures WordPress/WooPayments):

```bash
npm run up:recreate
```

This will:
1. Auto-configure your port and worktree ID (saved to `.env.local`)
2. Create/recreate the WordPress container
3. Run the setup script to install WordPress, WooCommerce, and WooPayments

For subsequent startups (container already configured):

```bash
npm run up
```

#### Step 4: Build JS assets

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

Your WordPress port is saved in `.env.local`. Check it with:

```
cat .env.local
```

Or it's displayed when you run `npm run up`.

### WordPress Admin

Open http://localhost:<YOUR_PORT>/wp-admin/ (check `.env.local` for your port, default is 8082)
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

#### Creating a new worktree

```bash
# Create the worktree
git worktree add ../my-feature-branch feature-branch

# Navigate to it
cd ../my-feature-branch

# Install dependencies
npm install

# Start WordPress (port auto-configured on first run)
npm run up
```

The `post-checkout` hook will automatically configure `.env.local` with a unique port.

#### Removing a worktree

Before removing a worktree, clean up its Docker resources:

```bash
cd /path/to/worktree
npm run worktree:cleanup
cd ..
git worktree remove /path/to/worktree
```

#### Customizing your worktree config

Edit `.env.local` to customize:
```bash
# Port for this worktree's WordPress instance
WP_PORT=8086

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

In a new terminal window run (replace PORT with your actual port from `.env.local`):

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

You can add local PHP scripts in the `docker/mu-plugins` directory since it's mounted as the `wp-content/mu-plugins` WordPress directory in your Docker container. These PHP scripts will be loaded automatically because they are treated as [WordPress must-use plugins](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/).

**Note:** Please make sure that you try to think of these scripts as _temporary solutions/helpers_ and not as permanent code to be run constantly (unless you are sure that is what you want).

One _recommended way_ of working with your collection of helper scripts is to take advantage of the fact that _WordPress will not automatically load PHP files_ in subdirectories of `wp-content/mu-plugins` (as it does with regular plugins in `wp-content/plugins`).

1. Create a new directory in `docker/mu-plugins` for your scripts, e.g. `docker/mu-plugins/local-helpers`. WordPress will not automatically load PHP files in subdirectories of `mu-plugins`, so you need to include them manually.
2. Create a new PHP file in `docker/mu-plugins`,e.g. `docker/mu-plugins/0-local-helpers.php`.
3. Add lines like `require_once __DIR__ . '/local-helpers/your-script.php';` to `docker/mu-plugins/0-local-helpers.php` to load your scripts.
4. Comment/uncomment the `require_once` lines to load the scripts you need for your particular itch.
5. Make sure you comment out any lines once you are finished with that itch to avoid unexpected/non-standard behavior on your local environment going forward - leftover helpers are not helpful!
