#!/usr/bin/env bash

# MySQL/MariaDB version detection utility functions
# This script can be sourced by other scripts to reuse the version detection logic

# Function to get the appropriate SSL flag based on MySQL/MariaDB version
get_mysql_ssl_flag() {
    # Check MySQL/MariaDB client version and set appropriate SSL flag
    local mysql_version_output=$(mysql --version 2>/dev/null)
    local mysql_version=""

    # Extract version from different possible formats:
    # mysql  Ver 8.0.43-0ubuntu0.22.04.1 for Linux on aarch64 ((Ubuntu))
    # mysql from 11.8.2-MariaDB, client 15.2 for debian-linux-gnu (aarch64) using  EditLine wrapper
    if echo "$mysql_version_output" | grep -q "MariaDB"; then
        # MariaDB format: extract the MariaDB version
        mysql_version=$(echo "$mysql_version_output" | grep -oP 'MariaDB, client \K[0-9]+\.[0-9]+' | head -1)
    else
        # MySQL format: extract the MySQL version
        mysql_version=$(echo "$mysql_version_output" | grep -oP 'Ver \K[0-9]+\.[0-9]+' | head -1)
    fi

    if [ -n "$mysql_version" ] && [ "$(echo "$mysql_version >= 8.0" | bc -l 2>/dev/null || echo "0")" = "1" ]; then
        echo "--ssl-mode=DISABLED"
    else
        echo "--skip-ssl"
    fi
}
