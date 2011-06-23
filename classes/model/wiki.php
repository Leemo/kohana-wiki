<?php defined('SYSPATH') or die('No direct script access.');

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

	protected $_scope_column = NULL;

	/**
	 * Base URL
	 *
	 * @var type
	 */
	protected $_base_url;

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
				array('not_empty')
			),
			'alias' => array
			(
				array('not_empty')
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
		return array();
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

	public function local_url($local_url)
	{
		$this->_local_url = $local_url;

		return $this;
	}

	public function scope($column, $value)
	{
		$this->_scope_column = $column;

		return $this
			->values($column, $value);
	}

	public function create_page($values, $expected = array('title', 'alias', 'markdown'))
	{
		$values['alias'] = self::word_to_uri($values['title']);

		$this
			->values($values, $expected)
			->save();

		return $values['alias'];
	}

	public function update_page($values, $expected = array('title', 'alias', 'markdown'))
	{
		$values['alias'] = self::word_to_uri($values['title']);

		$this
			->values($values, $expected)
			->save()
			->update_html();

		return $values['alias'];
	}

	public function base_url($base_url)
	{
		$this->_base_url = $base_url;

		return $this;
	}

	public function image_url($image_url)
	{
		$this->_image_url = $image_url;

		return $this;
	}

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

	public function update_html()
	{
		if ( ! $this->loaded())
		{
			return FALSE;
		}

		require Kohana::find_file('vendor', 'markdown/markdown');

		// Set wiki stats
		Wiki_Markdown::$base_url       = $this->_base_url;
		Wiki_Markdown::$image_url      = $this->_image_url;
		Wiki_Markdown::$existing_pages = $this->_existing_pages();
		Wiki_Markdown::$local_url      = $this->_local_url;

		$parser = new Wiki_Markdown;
		$html   = $parser->transform($this->markdown);

		$links  = $parser->local_uris();

		// Update link info
		DB::delete('wiki_links')
			->where('wiki_id', '=', $this->pk())
			->execute($this->_db);

		DB::update($this->_table_name)
			->set(array('html' => ''))
			->where($this->_primary_key, 'IN', DB::select('wiki_id')
				->from('wiki_links')
				->where('link', '=', $this->alias))
			->execute($this->_db);

		$insert = DB::insert('wiki_links', array('wiki_id', 'link'));

		foreach ($links as $link)
		{
			$insert->values(array($this->pk(), $link));
		}

		$insert->execute($this->_db);

		return $this->values(array(
			'html' => $html
		))->save();
	}

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
			->where($this->_scope_coumn, '=', $this->{$this->_scope_column});
	}

	protected function _existing_pages()
	{
		return $this->_apply_scope(DB::select('id', 'alias')
			->from($this->_table_name))
			->execute($this->_db)
			->as_array('id', 'alias');
	}

	public static function word_to_uri($text)
	{
		return preg_replace('/([^[:alnum:]_-]*)/', '', strtolower(str_replace(' ', '-', $text)));
	}

	protected function links($id)
	{
		return DB::select('id', 'alias')
			->from($this->_table_name)
			->where('id', 'IN', DB::expr(DB::select('remote_id')
				->from('wiki_links')
				->where('wiki_id', '=', DB::expr($this->_table_name.'.'.'id'))
				))
			->execute($this->_db)
			->as_array('id', 'alias');
	}

} // Model_Wiki