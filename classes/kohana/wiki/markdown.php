<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Custom Markdown parser for Kohana wiki.
 *
 * @package    Kohana/Wiki
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Kohana_Wiki_Markdown extends MarkdownExtra_Parser {

	/**
	 * @var  string  base url for links
	 */
	public static $local_url = ':page';

	/**
	 * @var  string  base url for images
	 */
	public static $image_url = '';

	/**
	 * Currently defined heading ids.
	 * Used to prevent creating multiple headings with same id.
	 * @var array
	 */
	protected $_heading_ids = array();

	/**
	 * @var  string   the generated table of contents
	 */
	protected static $_toc = '';

	/**
	 * Slightly less terrible way to make it so the TOC only shows up when we
	 * want it to.  set this to true to show the toc.
	 */
	public static $show_toc = false;

	/**
	 *
	 *
	 * @var array
	 */
	public $_local_uris = array();

	/**
	 *
	 * @var array
	 */
	public static $existing_pages = array();

	public function __construct()
	{
		// doImage is 10, add image url just before
		$this->span_gamut['doImageURL'] = 9;

		// doLink is 20, add image url just before
		$this->span_gamut['doInternalURL'] = 15;

		// Add API links
		$this->span_gamut['doAPI'] = 90;

		// Add note spans last
		$this->span_gamut['doNotes'] = 100;

		// PHP4 makes me sad. (c) Kohana Team
		// Gosh! Me to! (c) Alexey Popov, Leemo Developers Team
		parent::MarkdownExtra_Parser();
	}

	/**
	 * Callback for the heading setext style
	 *
	 * Heading 1
	 * =========
	 *
	 * @param  array    Matches from regex call
	 * @return string   Generated html
	 */
	function _doHeaders_callback_setext($matches)
	{
		if ($matches[3] == '-' && preg_match('{^- }', $matches[1]))
			return $matches[0];
		$level = $matches[3]{0} == '=' ? 1 : 2;
		$attr  = $this->_doHeaders_attr($id =& $matches[2]);

		// Only auto-generate id if one doesn't exist
		if(empty($attr))
			$attr = ' id="'.$this->make_heading_id($matches[1]).'"';

		// Add this header to the page toc
		$this->_add_to_toc($level,$matches[1],$this->make_heading_id($matches[1]));

		$block = "<h$level$attr>".$this->runSpanGamut($matches[1])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

	/**
	 * Callback for the heading atx style
	 *
	 * # Heading 1
	 *
	 * @param  array    Matches from regex call
	 * @return string   Generated html
	 */
	function _doHeaders_callback_atx($matches)
	{
		$level = strlen($matches[1]);
		$attr  = $this->_doHeaders_attr($id =& $matches[3]);

		// Only auto-generate id if one doesn't exist
		if(empty($attr))
			$attr = ' id="'.$this->make_heading_id($matches[2]).'"';

		// Add this header to the page toc
		$this->_add_to_toc($level,$matches[2],$this->make_heading_id($matches[2]));

		$block = "<h$level$attr>".$this->runSpanGamut($matches[2])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

	function _doAnchors_inline_callback($matches)
	{
		$whole_match = $matches[1];
		$link_text   = $this->runSpanGamut($matches[2]);
		$url         =  $matches[3] == '' ? $matches[4] : $matches[3];
		$title       =& $matches[7];

		$url = $this->encodeAttribute($url);

		$result = '<a href="'.$url.'"';

		if (stristr($url, '://'))
		{
			$result .= ' class="wiki_external"';
		}

		if (isset($title))
		{
			$title   = $this->encodeAttribute($title);
			$result .=  ' title="'.$title.'"';
		}

		$link_text = $this->runSpanGamut($link_text);
		$result   .= ">$link_text</a>";

		return $this->hashPart($result);
	}

	/**
	 * Makes a heading id from the heading text
	 * If any heading share the same name then subsequent headings will have an integer appended
	 *
	 * @param  string The heading text
	 * @return string ID for the heading
	 */
	function make_heading_id($heading)
	{
		$id = url::title($heading, '-', TRUE);

		if(isset($this->_heading_ids[$id]))
		{
			$id .= '-';

			$count = 0;

			while (isset($this->_heading_ids[$id]) AND ++$count)
			{
				$id .= $count;
			}
		}

		return $id;
	}

	/**
	 * Add the current base url to all local links.
	 *
	 *     [filesystem](about.filesystem "Optional title")
	 *
	 * @param   string  span text
	 * @return  string
	 */
	public function doInternalURL($text)
	{
		// URLs containing "://" are left untouched
		return preg_replace_callback('/'.
				'\[\['.             // opening brackets
					//'(([^\]]*?)\:)?'. // namespace (if any)
					'([^\]]*?)'.      // target
					'(\|([^\]]*?))?'. // title (if any)
				'\]\]'.             // closing brackets
				'([a-z]+)?'.        // any suffixes
				'/', array( & $this, '_doInternalURL_callback'), $text);
	}

	protected function _doInternalURL_callback($matches)
	{
		$text = (isset($matches[3])) ? $matches[3] : $matches[1];

		$uri  = $matches[1];

		if ( ! in_array($uri, $this->_local_uris)) $this->_local_uris[] = $uri;

		$class = ( ! in_array(UTF8::strtolower($uri), self::$existing_pages)) ? ' class="wiki_empty"' : NULL;

		return $this->hashPart('<a href="'.str_replace(':page', $uri, self::$local_url).'"'.$class.'>'.$text.'</a>');
	}

	/**
	 * Add the current base url to all local images.
	 *
	 *     ![Install Page](img/install.png "Optional title")
	 *
	 * @param   string  span text
	 * @return  string
	 */
	public function doImageURL($text)
	{
		// URLs containing "://" are left untouched
		return preg_replace('~(!\[.+?\]\()(?!\w++://)(\S*(?:\s*+".+?")?\))~', '$1'.self::$image_url.'$2', $text);
	}

	/**
	 * Parses links to the API browser.
	 *
	 *     [Class_Name], [Class::method] or [Class::$property]
	 *
	 * @param   string   span text
	 * @return  string
	 */
	public function doAPI($text)
	{
		return $text;
	}

	/**
	 * Wrap notes in the applicable markup. Notes can contain single newlines.
	 *
	 *     [!!] Remember the milk!
	 *
	 * @param   string  span text
	 * @return  string
	 */
	public function doNotes($text)
	{
		if ( ! preg_match('/^\[!!\]\s*+(.+?)(?=\n{2,}|$)/s', $text, $match))
		{
			return $text;
		}

		return $this->hashBlock('<p class="note">'.$match[1].'</p>');
	}

	protected function _add_to_toc($level, $name, $id)
	{
		self::$_toc[] = array(
			'level' => $level,
			'name'  => $name,
			'id'    => $id);
	}

	public function local_uris()
	{
		return $this->_local_uris;
	}

} // End Kodoc_Markdown
