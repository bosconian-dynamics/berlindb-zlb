<?php
namespace BosconianDynamics\BerlinDB_Zlb;

defined( 'ABSPATH' ) || exit;

if( ! class_exists( '\\WP_List_Table' ) )
	require_once ABSPATH . ' /wp-admin/includes/class-wp-list-table.php';

/**
 * A WP_List_Table implementation with BerlinDB bindings.
 *
 * @property array $actions A list of action configuration arrays.
 */
class List_Table extends \WP_List_Table {
	//  protected $compat_fields = [ '_args', '_pagination_args', 'screen', '_actions', '_pagination' ];

	/**
	 * A list of action configuration arrays.
	 *
	 * @var array
	 */
	protected $actions      = [];

	/**
	 * An associative array mapping column identifiers to column configuration arrays.
	 *
	 * @var array
	 */
	protected $columns      = [];

	/**
	 * A list of column identifiers determining the order in which columns should be displayed. Any
	 * configured columns whose identifiers do no appear in the list will be appended to the end of
	 * the display in registration order.
	 *
	 * @var array
	 */
	protected $column_order = [];

	/**
	 * Key of the transient in which admin notices for this list table are stored.
	 *
	 * @var string
	 */
	protected $opt_notices  = '';

	/**
	 * Key of the usermeta setting storing a user's prefered number of rows to print per page for
	 * this table.
	 *
	 * @var string
	 */
	protected $opt_per_page = '';

	/**
	 * The BerlinDB Query for this table.
	 *
	 * @var \BerlinDB\Database\Query
	 */
	protected $query;

	/**
	 * The BerlinDB Schema for this table.
	 *
	 * @var \BerlinDB\Database\Schema
	 */
	protected $schema;

	/**
	 * Constructor
	 *
	 * @param \BerlinDB\Database\Query $query The subject model's query.
	 * @param array                    $args Configuration arguments.
	 */
	public function __construct( $query, $args = [] ) {
		$defaults = [
			'actions'         => [],
			'columns'         => [],
			'column_order'    => [],
			'notices_option'  => "bdb_list_table_{$query->item_name_plural}_notices",
			'per_page_option' => "{$query->item_name}_rows_per_page",
			'plural'          => $query->item_name_plural,
			'singular'        => $query->item_name,
			'labels'          => [
				'singular' => self::key_to_name( $query->item_name ),
				'plural'   => self::key_to_name( $query->item_name_plural ),
			],
		];

		if( isset( $args['labels'] ) )
			$args['labels'] = \wp_parse_args( $args['labels'], $defaults['labels'] );

		$args = \wp_parse_args( $args, $defaults );

		parent::__construct( $args );

		$this->query        = $query;
		$this->schema       = new $query->table_schema();
		$this->opt_per_page = \sanitize_key( $args['per_page_option'] );
		$this->opt_notics   = \sanitize_key( $args['notices_option'] );

		/**
		 * Merge schema and configured column definitions.
		 */
		$schema_columns = \wp_list_pluck( $this->schema->columns, 'name' );

		// Register column configurations from arguments. Merges values with defaults from schema.
		if( isset( $args['columns'] ) ) {
			foreach( array_keys( $args['columns'] ) as $name ) {
				$this->add_column( $name, $args['columns'][ $name ] );
			}
		}

		// Register schema definitions with no corresponding configuration argument.
		foreach( $schema_columns as $name ) {
			if( $this->has_column( $name ) )
				continue;

			$this->add_column( $name );
		}

		// Set configured column order.
		if( isset( $args['column_order'] ) )
			$this->column_order = $args['column_order'];

		/**
		 * Options
		 */
		\add_screen_option(
			'per_page',
			[
				'label'   => self::key_to_name( $this->get_item_name( true ) ),
				'default' => 20,
				'option'  => $this->opt_per_page,
			]
		);

		/**
		 * Actions
		 */

		// Set up action links
		foreach( $args['actions'] as $name => $action )
			$this->add_action( $name, $action );

		// Process incoming actions
		$this->run_action( $this->current_action() );
	}

	/**
	 * Register a new action.
	 *
	 * @param string $name Action identifier.
	 * @param array  $args Action configuration.
	 * @return void
	 */
	public function add_action( $name, $args = [] ) {
		if( ! isset( $args['name'] ) )
			$args['name'] = $name;

		$this->actions[] = $args;
	}

	/**
	 * Register a column definition array.
	 *
	 * @param string $name The internal identifier for the column.
	 * @param array  $args A column definition array.
	 * @return void
	 * @throws \Error On duplicate column name registration.
	 */
	public function add_column( $name, $args = [] ) {
		if( $this->has_column( $name ) )
			throw new \Error( "Column '$name' already exists." );

		$column = \wp_parse_args(
			$args,
			$this->get_schema_column( $name )
		);

		if( ! isset( $column['name'] ) )
			$column['name'] = $name;

		if( ! isset( $column['label'] ) )
			$column['label'] = self::key_to_name( $name );

		$this->columns[ $name ] = $column;
		$this->column_order[]   = $name;
	}

	/**
	 * Retrieve the string identifier associated with the data.
	 *
	 * @param boolean $plural Whether to retrieve the plural identifier.
	 * @return string The identifier string.
	 */
	public function get_item_name( $plural = false ) {
		if( $plural )
			return $this->_args['plural'];

		return $this->_args['singular'];
	}

	/**
	 * Check if this list table has a registered configuration for an identifier.
	 *
	 * @param string $name The column identifier.
	 * @return boolean
	 */
	public function has_column( $name ) {
		return isset( $this->columns[ $name ] );
	}

	/**
	 * Retrieve a column configuration for a schema column.
	 *
	 * @param string $name The name of the schema column.
	 * @return array A column configuration array, or an empty array if none was found.
	 */
	private function get_schema_column( $name ) {
		foreach( $this->schema->columns as $column ) {
			if( $column->name === $name ) {
				return [
					'name'     => $column->name,
					'label'    => empty( $column->comment ) ? self::key_to_name( $column->name ) : $column->comment,
					'sortable' => $column->sortable,
					'primary'  => $column->primary,
					'default'  => $column->default,
				];
			}
		}

		return [];
	}

	/**
	 * Retrieve the number of rows to display per page from usermeta.
	 *
	 * @return int
	 */
	public function get_rows_per_page() {
		return $this->get_items_per_page( $this->opt_per_page, 20 );
	}

	/**
	 * Retrieve an action configuration by identifier.
	 *
	 * @param string $name The action identifier.
	 * @return array|null
	 */
	protected function get_action( $name ) {
		foreach( $this->actions as $action ) {
			if( $action['name'] === $name )
				return $action;
		}

		return null;
	}

	/**
	 * Execute action callback and hooks for the given action identifier. Redirects back to the
	 * current URL with action-related query variables stripped off of the query string after all
	 * hooks have completed.
	 *
	 * @param string $name
	 * @return void
	 */
	public function run_action( $name ) {
		$action = $this->get_action( $name );
		$value  = null;

		// Bail on unconfigured action.
		if( ! isset( $action ) )
			return;

		$data  = $_REQUEST['action_data'];
		$value = $data;

		if( isset( $action['callback'] ) )
			$value = call_user_func( $action['callback'], $data, $name );

		do_action( 'bdbz_list_table_action', $name, $value );
		do_action( "bdbz_list_table_action_{$name}", $value );

		\wp_safe_redirect( \remove_query_arg( [ 'action', 'action_data' ] ) );
		exit;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return void
	 */
	public function prepare_items() {
		// Retrieve pagination values.
		$per_page = $this->get_rows_per_page();
		$offset   = ( $this->get_pagenum() - 1 ) * $per_page;

		// Set up the query.
		$query_args = [
			'number' => $per_page,
			'offset' => $offset,
		];

		// Ordering parameters.
		if( ! empty( $_GET['orderby'] ) ) {
			$orderby = \sanitize_key( \wp_unslash( $_GET['orderby'] ) );

			if( in_array( $orderby, array_keys( $this->columns ), true ) ) {
				$query_args['orderby'] = $orderby;
			}
		}

		if( ! empty( $_GET['order'] ) ) {
			$order = \sanitize_text_field( \wp_unslash( $_GET['order'] ) );

			if( in_array( $order, [ 'asc', 'desc' ], true ) ) {
				$query_args['order'] = $order;
			}
		}

		$this->set_pagination_args(
			[
				'total_items' => $this->query->query( array_merge( $query_args, [ 'count' => true ] ) ),
				'per_page'    => $per_page,
			]
		);

		$rows = $this->query->query( $query_args );

		if( $this->query->found_items === 0 )
			return;

		$this->items = $rows;
	}

	/**
	 * Retrieves bulk action configurations.
	 *
	 * @return void
	 */
	protected function get_bulk_actions() {
		return array_reduce(
			$this->actions,
			function( $actions, $action ) {
				if( ! empty( $action['bulk'] ) )
					$actions[ $action['name'] ] = $action['label'];

				return $actions;
			},
			[]
		);
	}

	/**
	 * Retrieve the first column configuration with the specified key/value pair. If no key is
	 * specified, defaults to matching against the column identifier.
	 *
	 * @param string $value The configuration property value to match against.
	 * @param string $key The configuration property key to match against. Defaults to `name`.
	 * @return array|null
	 */
	protected function get_column_by( $value, $key = 'name' ) {
		$columns = array_values( $this->filter_columns( [ $key => $value ] ) );

		if( count( $columns ) )
			return $columns[0];

		return null;
	}

	/**
	 * Retrieve column definitions that have properties matching a set of key/value pairs.
	 *
	 * @param array       $properties
	 * @param string|null $field A property key.
	 * @return array An associative array mapping column names to column configurations for matching columns. If $field is set,
	 */
	protected function filter_columns( $properties = [], $field = null ) {
		$columns = $this->columns;

		if( count( $properties ) ) {
			$columns = array_filter(
				$columns,
				function( $column ) use( $properties ) {
					foreach( $properties as $key => $value ) {
						if( ! isset( $column[ $key ] ) || $column[ $key ] !== $value )
							return false;
					}

					return true;
				}
			);
		}

		if( $field ) {
			$columns = array_reduce(
				$columns,
				function( $fields, $column ) use( $field ) {
					$fields[] = $column[ $field ];
					return $fields;
				},
				[]
			);
		}

		return $columns;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param BerlinDB\Database\Row $item Shaped item object.
	 * @param string                $column_name The column identifier.
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		$value  = isset( $item->$column_name ) ? $item->$column_name : null;
		$column = $this->get_column_by( $column_name, 'name' );

		if( $column ) {
			if( isset( $column['value'] ) ) {
				if( is_callable( $column['value'] ) )
					$value = call_user_func( $column['value'], $value, $item, $column_name );
				elseif( ! isset( $value ) )
					$value = $column['value'];
			}

			if( ! isset( $value ) && isset( $column['default'] ) )
				$value = $column['default'];
		}

		$value = \apply_filters( "bdb_list_table_field_value_{$column_name}", $value, $item, $column_name );

		return $value;
	}

	/**
	 * Checkbox column value callback. Prints the markup for row selection for the column identified
	 * as `cb`.
	 *
	 * @param \BerlinDB\Database\Row $item Shaped item.
	 * @return void
	 */
	protected function column_cb( $item ) {
		$primary_column = $this->get_primary_column_name();
		$item_key       = $item->$primary_column;
		?>
		<label class="screen-reader-text" for="cb-select-<?php echo $item_key; ?>">
				<?php
						/* translators: %s: Post title. */
						printf( __( 'Select Item %s' ), $item_key );
				?>
		</label>
		<input id="cb-select-<?php echo $item_key; ?>" type="checkbox" name="<?php echo $primary_column; ?>[]" value="<?php echo $item_key; ?>" />
		<?php
	}

	protected function extra_tablenav( $which ) {
		submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = [
			'cb' => true,
		];

		foreach( $this->column_order as $name ) {
			if( ! isset( $this->columns[ $name ] ) )
				continue;

			$columns[ $name ] = $this->columns[ $name ]['label'];
		}

		return $columns;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return void
	 */
	protected function get_hidden_columns() {
		return [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return void
	 */
	protected function get_sortable_columns() {
		return array_map(
			function( $column ) {
				return [ $column['name'], ! empty( $column['primary'] ) ];
			},
			$this->filter_columns( [ 'sortable' => true ] )
		);
	}

	/**
	 * Retrieve the configuration for the primary column.
	 *
	 * @return array
	 */
	protected function get_primary_col() {
		static $column;

		if( ! isset( $column ) )
			$column = $this->get_column_by( true, 'primary' );

		return $column;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \BerlinDB\Database\Row $item Shaped item.
	 * @param string                 $column_name Current column identifier.
	 * @param string                 $primary Primary column identifier.
	 * @return void
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		$actions = array_reduce(
			$this->actions,
			function( $actions, $action ) use( $item, $column_name, $primary ) {
				if( empty( $action['row'] ) )
					return $actions;

				$row_config = $action['row'];

				if(
					( is_bool( $row_config ) && $row_config && $column_name === $primary )
					|| ( is_string( $row_config ) && $column_name === $row_config )
				) {
					$primary = $this->get_primary_col();

					$actions[ $action['name'] ] = sprintf(
						'<a href="%1$s">%2$s</a>',
						$this->get_action_url(
							$action,
							[
								$item->{$primary['name']},
							]
						),
						$action['label']
					);
				}

				return $actions;
			},
			[]
		);

		return $this->row_actions( $actions );
	}

	/**
	 * Constructs a URL which will execute a specified action with an optional payload.
	 *
	 * @param string|array|null $action_name Action identifier or configuration object.
	 * @param  mixed            $data Data payload.
	 * @return string The URL.
	 */
	public function get_action_url( $action_name = null, $data = null ) {
		$url = '?' . $_SERVER['QUERY_STRING'];

		if( isset( $action_name ) ) {
			if( is_array( $action_name ) )
				$action_name = $action_name['name'];

			$url = \add_query_arg( 'action', $action_name, $url );
		}

		if( isset( $data ) ) {
			$url = \add_query_arg(
				'action_data',
				$data,
				$url
			);
		}

		return $url;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return void
	 */
	protected function get_default_primary_column_name() {
		$column = $this->get_primary_col();

		if( isset( $column ) && isset( $column['name'] ) )
			return $column['name'];

		return parent::get_default_primary_column_name();
	}

	public static function key_to_name( $key ) {
		return preg_replace_callback(
			'/(^|[\-_])(\w)/',
			function( $m ) {
				switch( $m[1] ) {
					case '-':
					case '_':
						return ' ' . strtoupper( $m[2] );
				}

				return strtoupper( $m[2] );
			},
			$key
		);
	}
}
