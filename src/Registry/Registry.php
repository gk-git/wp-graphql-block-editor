<?php
namespace WPGraphQL\BlockEditor\Registry;

use Exception;
use WP_Block_Type;
use WPGraphQL\BlockEditor\Blocks\Block;
use WPGraphQL\BlockEditor\Type\InterfaceType\EditorBlockInterface;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Utils\Utils;

/**
 * Class Registry
 *
 * @package WPGraphQL\BlockEditor\Registry
 */
class Registry {

	/**
	 * @var TypeRegistry
	 */
	public $type_registry;

	/**
	 * @var array
	 */
	public $registered_blocks;

	/**
	 * Registry constructor.
	 *
	 * @param TypeRegistry $type_registry
	 */
	public function __construct( TypeRegistry $type_registry ) {
		$this->type_registry = $type_registry;
	}

	/**
	 * @throws Exception
	 */
	public function init() {

//		register_graphql_object_type( 'EditorBlockSpacing', [
//			'fields' => [
//				'padding' => [
//					'type' => 'Boolean',
//				],
//			],
//		]);

		register_graphql_object_type( 'EditorBlockSupports', [
			'fields' => [
				'inserter' => [
					'type' => 'Boolean',
				],
				'multiple' => [
					'type' => 'Boolean',
				],
				'anchor' => [
					'type' => 'Boolean',
				],
//				'align' => [
//					'type' => 'BlockAlignment',
//				],
//				'__experimentalSelector' => [
//					'type' => 'String',
//				],
//				'__experimentalFontFamily' => [
//					'type' => 'Boolean',
//				],
				'fontSize' => [
					'type' => 'Boolean',
				],
				'customClassName' => [
					'type' => 'Boolean',
				],
				'html' => [
					'type' => 'Boolean',
				],
				"reusable" => [
					'type' => 'Boolean',
				],
//				"spacing" => [
//					'type' => 'BlockEditorSpacing'
//				],

			]
		]);

		// Register the EditorBlock Interface
		EditorBlockInterface::register_type( $this->type_registry );

		$this->pass_blocks_to_context();
		$this->register_block_types();
		$this->add_block_fields_to_schema();

	}

	/**
	 * This adds the WP Block Registry to AppContext
	 *
	 * @return void
	 */
	public function pass_blocks_to_context() {

		add_filter( 'graphql_app_context_config', function( $config ) {
			$config['registered_editor_blocks'] = $this->registered_blocks;
			return $config;
		});

	}

	/**
	 * Register Block Types to the GraphQL Schema
	 *
	 * @return void
	 */
	protected function register_block_types() {

		$block_registry = \WP_Block_Type_Registry::get_instance();
		$this->registered_blocks = $block_registry->get_all_registered();

		if ( empty( $this->registered_blocks ) || ! is_array( $this->registered_blocks ) ) {
			return;
		}

		foreach ( $this->registered_blocks as $block ) {
			$this->register_block_type( $block );
		}

	}

	/**
	 * Register a block from the Gutenberg server registry to the WPGraphQL Registry
	 *
	 * @param WP_Block_Type $block
	 */
	protected function register_block_type( WP_Block_Type $block ) {

		$block_name = isset( $block->name ) && ! empty( $block->name ) ? $block->name : 'Core/HTML';

		$type_name = preg_replace( '/\//', '', lcfirst( ucwords( $block_name, '/' ) ) );
		$type_name = Utils::format_type_name( $type_name );

		$class_name = Utils::format_type_name( $type_name );
		$class_name = '\\WPGraphQL\\BlockEditor\\Blocks\\' . $class_name;

		/**
		 * This allows 3rd party extensions to hook and and provide
		 * a path to their class for registering a field to the Schema
		 */
		$class_name = apply_filters( 'graphql_editor_blocks_block_class', $class_name, $block, $this );

		if ( class_exists( $class_name ) ) {
			new $class_name( $block, $this );
		} else {
			new Block( $block, $this );
		}

	}

	/**
	 * Adds Block Fields to the WPGraphQL Schema
	 *
	 * @return void
	 */
	public function add_block_fields_to_schema() {

		// Get Post Types that are set to Show in GraphQL and Show in REST
		// If it doesn't show in REST, it's not block-editor enabled
		$block_editor_post_types = get_post_types([ 'show_in_graphql' => true, 'show_in_rest' => true ], 'objects' );

		$supported_post_types = [];

		if ( empty( $block_editor_post_types ) || ! is_array( $block_editor_post_types ) ) {
			return;
		}

		// Iterate over the post types
		foreach ( $block_editor_post_types as $block_editor_post_type ) {

			// If the post type doesn't support the editor, it's not block-editor enabled
			if ( ! post_type_supports( $block_editor_post_type->name, 'editor' ) ) {
				continue;
			}

			if ( ! isset( $block_editor_post_type->graphql_single_name ) ) {
				continue;
			}

			$supported_post_types[] = $block_editor_post_type;
		}

		// If there are no supported post types, early return
		if ( empty( $supported_post_types ) ) {
			return;
		}

		// Register the `WithBlockEditor` Interface to the supported post types
		//
		// @todo: It would be nice to know which blocks are supported for each location.
		// For example, if Block A is supported on Post Type A but not Post Type B, users
		// should not see Block A in the possible blocks for Post Type B.
		// So, instead of one generic Interface to return all registered blocks, it would be nice
		// to show the blocks that are truly possible to be returned for that location
		register_graphql_interfaces_to_types( 'WithEditorBlocks', wp_list_pluck( $supported_post_types, 'graphql_single_name' ) );

	}

}
