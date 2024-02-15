#!/usr/bin/env bash

# Helper functions
function colorize_red() {
	local message=$1
	echo -e "\033[31m$message\033[0m"
}

function colorize_green() {
	local message=$1
	echo -e "\033[32m$message\033[0m"
}

function colorize_blue() {
	local message=$1
	echo -e "\033[34m$message\033[0m"
}

function colorize_yellow() {
	local message=$1
	echo -e "\033[33m$message\033[0m"
}

function log_message() {
	local message=$1
	echo -e "$message"
}

function log_error() {
	local message=$1
	colorize_red "$message"
}

function log_info() {
	local message=$1
	colorize_blue "$message"
}

function log_success() {
	local message=$1
	local prefix=$(colorize_green "Success:")
	echo -e "\033[1;32m$prefix\033[0m $message"
}

function log_block() {
	local message=$1
	local message_length=$((${#message} + 5))

	echo -e "\n# $message"
	printf '%.0s-' $(seq 1 $message_length)
	echo
}

function log_step() {
	local message=$1
	colorize_blue "\n###--> $message"
}

function log_step_success() {
	colorize_green "--> Success!\n"
}

function log_step_fail() {
	colorize_red "--> Failed!\n"
}

function server_cli() {
    docker exec -u www-data "$SERVER_CONTAINER" "$@"
}

function client_cli() {
    docker exec -u www-data "$CLIENT_CONTAINER" "$@"
}
