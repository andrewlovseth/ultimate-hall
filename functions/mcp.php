<?php
/**
 * MCP server for Hall of Fame content: /wp-json/uhof/mcp
 *
 * Serves each uhof/* ability as its own MCP tool, so Claude Desktop sees
 * "add-result", "find-team" and so on directly. Needs the MCP Adapter plugin
 * (wordpress.org/plugins/mcp-adapter); without it this file does nothing.
 * Clients authenticate with a WordPress Application Password.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'mcp_adapter_init',
	function ( $adapter ) {
		if ( ! class_exists( '\WP\MCP\Transport\HttpTransport' ) ) {
			return;
		}
		$adapter->create_server(
			'uhof',
			'uhof',
			'mcp',
			'Ultimate Hall of Fame',
			'Find, create and edit Hall of Fame members, teams, tournaments and induction classes. Look things up before creating them, preview merges before confirming them, and read a member before changing rows. Every change is logged and can be undone.',
			'1.0.0',
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			uh_ability_names()
		);
	}
);
