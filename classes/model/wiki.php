<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Wiki link model.
 *
 * @package    Kohana/Wiki
 * @category   Base
 * @author     Alexey Popov
 * @copyright  (c) 2009 Leemo Studio <http://leemo-studio.net>
 * @license    http://www.opensource.org/licenses/bsd-license.php
 */
class Model_Wiki extends ORM {

	/**
	 * Table, that store current objects
	 *
	 * @var string
	 */
	protected $_table_name = 'wiki';

	/**
	 * Created time row
	 *
	 * @var array
	 */
	protected $_created_column = array
	(
		'column' => 'created',
		'format' => TRUE
	);

	/**
	 * Updated time row
	 *
	 * @var array
	 */
	protected $_updated_column = array
	(
		'column' => 'modified',
		'format' => TRUE
	);

	/**
	 * A wiki page can has many links to the any wiki pages
	 *
	 * @var array Relationhips
	 */
	protected $_has_many = array
	(
		'links' => array('model' => 'wiki_link')
	);

	protected $_scope_column = 'scope';

	/**
	 * Image URL
	 *
	 * @var type
	 */
	protected $_image_url;

	/**
	 * Local wiki URL
	 *
	 * @var type
	 */
	protected $_local_url = ':page';

	/**
	 * Rules of the wiki model
	 *
	 * @return array Rules
	 */
	public function rules()
	{
		return array
		(
			'title' => array
			(
				array('not_empty'),
				array('min_length', array(':value', 4)),
				array('max_length', array(':value', 128)),
				array(array($this, 'title_available'), array(':validation', ':field')),
				array(array($this, 'is_url'), array(':validation', ':field', ':value')),
			),
			'markdown' => array
			(
				array('not_empty')
			),
		);
	}

	/**
	 * Filters to run when data is set in this model
	 *
	 * @return array Filters
	 */
	public function filters()
	{
		return array
		(
			'title'     => array
			(
				array('strip_tags'),
				array('trim')
			),
			'markdown' => array
			(
				array('trim')
			)
		);
	}

	public function html()
	{
		if ( ! $this->loaded())
		{
			return FALSE;
		}

		if (empty($this->html)) $this->update_html();

		return $this->html;
	}

	/**
	 * Apply scope condition (for many different wiki)
	 *
	 * @param     string       $value    Scope value
	 * @return    Model_Wiki
	 */
	public function scope($value)
	{
		$this->{$this->_scope_column} = $value;

		return $this;
	}

	/**
	 * Creates a wiki page
	 *
	 * @param    array      $values     Page values
	 * @param    array      $expected   Expected rows
	 * @return   Model_Wiki
	 */
	public function create_page($values, $expected = array('title', 'markdown'))
	{
		$this
			->values($values, $expected)
			->scope($this->{$this->_scope_column})
			->save()
			->update_html();
	}

	/**
	 * Updates current wiki page
	 *
	 * @param    array      $values     Page values
	 * @param    array      $expected   Expected rows
	 * @return   Model_Wiki
	 */
	public function update_page($values, $expected = array('title', 'markdown'))
	{
		if ( ! $this->loaded())
		{
			return FALSE;
		}

		$expected[] = $this->_scope_column;

		return $this
			->values($values, $expected)
			->scope($this->{$this->_scope_column})
			->save()
			->update_html();
	}

	/**
	 * Removes wiku page
	 *
	 * @return Model_Wiki
	 */
	public function delete()
	{
		if ( ! $this->loaded())
		{
			return FALSE;
		}

		$this->_clear_referrers();

		parent::delete();

		return $this;
	}

	/**
	 * Sets image url
	 *
	 * @param    string       $image_url    Image URL
	 * @return   Model_Wiki
	 */
	public function image_url($image_url)
	{
		$this->_image_url = $image_url;

		return $this;
	}

	/**
	 * Sets local url for internal wiki pages.
	 *
	 * Local URL - is a string in which the expression :page
	 * will be replaced by the title of the wiki pages.
	 *
	 * Example:
	 * ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	 * $wiki = ORM::factory('Model_Wiki')
	 *   ->local_url(Route::get('default')->uri(array(
	 *     'controller' => 'wiki',
	 *     'action'     => 'view',
	 *     'id'         => ':page'
	 *     )));
	 * ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	 *
	 * @param type $local_url
	 * @return Model_Wiki
	 */
	public function local_url($local_url)
	{
		$this->_local_url = $local_url;

		return $this;
	}

	/**
	 * Updates markdown-data of current page
	 * then updates HTML-data
	 *
	 * @param    string   $text   Wiki page text
	 * @return   Model_Wiki
	 */
	public function update_markdown($text)
	{
		if ( ! $this->loaded())
		{
			return FALSE;
		}

		return $this
			->values(array('markdown' => $text))
			->save()
			->update_html();
	}

	/**
	 * Recompiles HTML-text of current wiki page
	 *
	 * @return Model_Wiki
	 */
	public function update_html()
	{
		if ( ! $this->loaded())
		{
			return FALSE;
		}

		require Kohana::find_file('vendor', 'markdown/markdown');

		// Set wiki stats
		Wiki_Markdown::$image_url      = $this->_image_url;
		Wiki_Markdown::$existing_pages = $this->_existing_pages();
		Wiki_Markdown::$local_url      = $this->_local_url;

		$parser = new Wiki_Markdown;

		// XSS protection
		$parser->no_markup   = TRUE;
		$parser->no_entities = TRUE;

		$html   = $this->_min_html($parser->transform($this->markdown));

		$links  = $parser->local_uris();

		// TODO transaction

		// Clear referrers
		$this->_clear_referrers();

		// Delete links data
		DB::delete('wiki_links')
			->where('wiki_id', '=', $this->pk())
			->execute($this->_db);

		if (sizeof($links) > 0)
		{
			// Insert new links data
			$insert = DB::insert('wiki_links', array('wiki_id', 'link'));

			foreach ($links as $link)
			{
				$insert->values(array($this->pk(), $link));
			}

			$insert->execute($this->_db);
		}

		return $this->values(array(
			'html' => $html
		))->save();
	}

	/**
	 * Returns all wiki-links of current wiki page
	 *
	 * @param    int    $id
	 * @return   array
	 */
	protected function links($id)
	{
		if ($id instanceof $this)
		{
			$id = $id->pk();
		}

		return DB::select('id', 'title')
			->from($this->_table_name)
			->where('id', 'IN', DB::expr(DB::select('remote_id')
				->from('wiki_links')
				->where('wiki_id', '=', DB::expr($this->_table_name.'.'.'id'))
				))
			->execute($this->_db)
			->as_array('id', 'title');
	}

	/**
	 * Imposes a scope-condition on current sql- or orm-request
	 *
	 * @param    Database_Query_Builder_Select|Model_Wiki     $wiki
	 * @return   Database_Query_Builder_Select
	 */
	protected function _apply_scope($wiki)
	{
		if (! $wiki instanceof $this AND
			! $wiki instanceof Database_Query_Builder_Select)
		{
			return FALSE;
		}

		if ( ! isset($this->_scope_column))
		{
			return $wiki;
		}

		return $wiki
			->where($this->_scope_column, '=', $this->{$this->_scope_column});
	}

	/**
	 * Does the reverse of _unique_key_exists() by triggering error
	 * if project title exists.
	 * Validation callback.
	 *
	 * @param   Validation  Validation object
	 * @param   string      Field name
	 * @return  void
	 */
	public function title_available(Validation $validation, $field)
	{
		if ($this->_unique_key_exists($validation[$field], 'title'))
		{
			$validation->error($field, 'available', array($validation[$field]));
		}
	}

	/**
	 * Trigger error if title is url
	 *
	 * @param   Validation  Validation object
	 * @param   string      Field name
	 * @param   string      Field value
	 * @return  void
	 */
	public function is_url(Validation $validation, $field, $value)
	{
		$data = parse_url($value);

		if (isset($data['scheme']) AND isset($data['host']))
		{
			$validation->error($field, 'not_url', array($validation[$field]));
		}
	}

	public function find()
	{
		$this->where($this->_scope_column, '=', $this->{$this->_scope_column});

		return parent::find();
	}

	public function find_all()
	{
		$this->where($this->_scope_column, '=', $this->{$this->_scope_column});

		return parent::find_all();
	}


	/**
	 * Returns all existing pages of current wiki
	 *
	 * @return array
	 */
	protected function _existing_pages()
	{
		return array_map(create_function('$a', 'return UTF8::strtolower($a);'), $this->_apply_scope(DB::select('id', 'title')
			->from($this->_table_name))
			->execute($this->_db)
			->as_array('id', 'title'));
	}

	/**
	 * Tests if a unique key value exists in the database.
	 *
	 * @param   mixed    the value to test
	 * @param   string   field name
	 * @return  boolean
	 */
	protected function _unique_key_exists($value, $field = NULL)
	{
		if ($field === NULL)
		{
			// Automatically determine field by looking at the value
			$field = $this->unique_key($value);
		}

		return (bool) DB::select(array('COUNT("*")', 'total_count'))
			->from($this->_table_name)
			->where($field, '=', $value)
			->where($this->_primary_key, '!=', $this->pk())
			->where($this->_scope_column, '=', $this->{$this->_scope_column})
			->execute($this->_db)
			->get('total_count');
	}

	/**
	 * Clear html code in all referred wiki pages
	 *
	 * @return void
	 */
	protected function _clear_referrers()
	{
		DB::update($this->_table_name)
			->set(array('html' => ''))
			->where($this->_primary_key, 'IN', DB::select('wiki_id')
				->from('wiki_links')
				->where('link', '=', $this->title))
			->execute($this->_db);
	}

	/**
	 * Removes unnecessary whitespaces from HTML code.
	 * DB space is not infinite.
	 *
	 * @param    string   $html  HTML to minimize
	 * @return   string   Minimized HTML
	 */
	protected function _min_html($html)
	{
		return preg_replace('/(\s+)?(\<.+\>)(\s+)?/', '$2', $html);
	}

} // Model_Wiki