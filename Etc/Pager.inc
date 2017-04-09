<?php
/**
 * In this file two classes are defined:
 *      1: cls_Pager to make pagerja
 *      2: cls_Template to work with templates
 *
 * @package template.class.inc
 * @author Ratko Rudic, 28.11.2003
 */


/**
 * Ta class makes pager for jumping after pages.
 * Look at help file "template.class.html"
 *
 * Added, 22.2.2008
 * - every "link" is enslosed in span with specific class
 *	- first: jump to first page
 *	- previous: jump to previous set of pages
 *	- current: current page
 *	- normal: any other page to click
 *	- next: jump to next set of pages
 *	- last: jump to last page
 *
 */

class cls_Pager
{
	// number of all items (all_items / items_per_page = nb_pages)
	var $_all_items = 0;

	// items displayed on page
	var $_items_on_page = 20;

	// default number of pages
	var $_pages = 10;

	// href -> Where href on links of pager should point to. If it is "", it's inicialised to current page.
	var $_href = "";

	// for  advanced use --> if the link is 'javascriptdo_smth("a=1")', then 'javascriptdo_smth("a=1' v _href in '")' goes to _href_post
	var $_href_post = "";

	// we can add anchor to link
	var $_anchor = "";

	// name of varialbe, where we save on which page we are
	var $_page_var = "page";

	// name of variable, where we save number of all elements, we have to display
	var $_all_items_var = "p_all_items";

	// we don't condider this parameters in qs
	var $skip_params = Array();

	// Additional parameters to <a> tag. YOu can put in also tag: ##URL##, ##PAGE## or ##P_ALL_ITEMS##.
	var $_additional_params = "";

	// set this to vertical if you want pager to be vertical
	var $_orientation = 'horizontal';

	//use specific classes for any part of the pager -> you can overide them with call of classes function
	var $_style = Array(	"first" => "first",
				"previous" => "previous",
				"current" => "selected",
				"normal" => "normal",
				"next" => "next",
				"last" => "last");

	//use default navigation to be used
	// if first = FP, then it is replaced with first page
	// if last = LP, then it is replaced with last page
	var $_navig = Array(	"first" => "&lt;&lt;",
				"previous" => "&lt;",
				"next" => "&gt;",
				"last" => "&gt;&gt;");

	// number of pages
	var $max_page = 0;
        
        //Boolean for next (currentpage+1) and previous (currentpage-1)
        //added for catchup search pagination
        var $enable_next_previous = false;

	/**
	 * Constructor
	 * We set number of all and number of items on individual page
	 */
	function cls_Pager($all_items, $items_on_page = 0,$next_previous_flag=false)
	{
		global $PAGER_NAVIGATOR;
		if ($items_on_page) {
			$this->_items_on_page = (int)$items_on_page;
		}

		$this->_all_items = (int)$all_items;

		if (isset($PAGER_NAVIGATOR))
			$this->_navig = array_merge($this->_navig,$PAGER_NAVIGATOR);
                        
                //Navigating to next page and previous page added for catchup       
                if ($next_previous_flag)
                {
                        $this->enable_next_previous = true;
                }
		return true;
	}

	/**
	 * Function sets NAMES of variables, with which pager knows on  which page it is and how much items are there.
	 * Usually this are two variables "page" and "p_all_items", if there are more seperated pagers on one page,
	 *      every has to have its own variables
	 */
	function set_var_names($page_var = "page", $all_items_var = "p_all_items") {
		$this->_page_var = $page_var;
		$this->_all_items_var = $all_items_var;
	}

	/**
	 * Inicialise href to current page
	 */
	function init_href() {

		// we set href to current page.
		$this->_href = $_SERVER["PHP_SELF"];
		$this->_href = str_replace("//", "/", $this->_href);

		foreach ($_POST as $key => $val)
		{
			if ($key == $this->_page_var) continue;
			if ($key == $this->_all_items_var) continue;
			if ($key == "PHPSESSID") continue;
			if (in_array($key, $this->skip_params)) continue;

			if (strpos($this->_href,"?")===false)
				$this->_href .= "?" . $key . "=" . $val;
			else
				$this->_href .= "&amp;" . $key . "=" . $val;
		}

		foreach ($_GET as $key => $val)
		{
			if ($key == $this->_page_var) continue;
			if ($key == $this->_all_items_var) continue;
			if ($key == "PHPSESSID") continue;
			if (in_array($key, $this->skip_params)) continue;

			if (strpos($this->_href,"?")===false)
				$this->_href .= "?" . $key . "=" . $val;
			else
				$this->_href .= "&amp;" . $key . "=" . $val;
		}

		if (strpos($this->_href,"?")===FALSE) $this->_href .= "?";
	}

	/**
	 * We set href for pager
	 */
	function href($href)
	{
		if (strpos($href,"\")")===FALSE)
			$this->_href = $href;
		else {
			$this->_href = substr($href, 0, strpos($href,"\")"));
			$this->_href_post = substr($href, strpos($href,"\")"));
		}
	}

	/**
	 * We set href for pager
	 */
	function anchor($anchor)
	{
		$this->_anchor = $anchor;
	}
		
	/**
	 * Overide default classes for any link od the pager
	 * @param $key name of the class to implement; possible values:
		*first,last -> first/last page
		*previous,next -> previous/next set of pages
		*current,normal -> selected/unselected page
		*plus,minus -> next/previous page
	 * @param $value value of the class
	*/
	function style($key,$value)
	{
		if ($key && $value)
			$this->_style[$key] = $value;
	}

	/**
	 * Set number of pages per page
	 */
	function pages($pages)
	{
		if ($pages == (int)$pages)
			$this->_pages = $pages;
	}

	/**
	 * we create HTML for pager
	 */
	function create($current_page = 0, $append_url_params = true)
	{
                global $_;
                if ($current_page==0)
			$current_page = (int)$_REQUEST[$this->_page_var];
		if (!$current_page) $current_page = 1;

		$this->max_page = ceil($this->_all_items / $this->_items_on_page);

		// which pages we display ($this->_pages links on page)
		if (!$current_page) $current_page=1;
		$current_page = (int)$current_page;

		//if there is only one page, return ""
		if ($this->max_page == 1 || $this->max_page == 0)
			return "";

		//set number of pages per page
		$sklop_links=$this->_pages;

		// which is current assembly (it starts with assembly 0)
		$sklop = floor($current_page / (float)$sklop_links);
		if (($current_page % $sklop_links)==0) $sklop--;

		// we check url
		if (!$this->_href) $this->init_href();
                
                //link to go to previous page (currentpage-1) added for catchup
                //search pagination
                if($this->enable_next_previous === true)
                {
                        //link to go to previous page (currentpage-1)
        		if($current_page>1)
        		{
        			$links_pages .= "<span class='".$this->_style["previous"]."'><a href='" . $this->mk_link($this->_href, ($current_page-1), $append_url_params) . "' ".$this->get_additional_params($this->_href, $sklop_links*$sklop).">".$_['PREVIOUS']."</a></span> ";
        		}
                }
		//only if you are not on the first set of pages render first part of it (<<)
		if ($current_page > $this->_pages) {

			//if last = NB put there number of last page
			if ($this->_navig["first"] == "FP")
				$this->_navig["first"] = 1;

			// jump to first page
			if ($this->_orientation == 'horizontal')
				$links_pages .= "<span class='".$this->_style["first"]."'><a href='" . $this->mk_link($this->_href, 1, $append_url_params) . "' ".$this->get_additional_params($this->_href, 1).">".$this->_navig["first"]."</a></span> ";
			else
				$links_pages .= "<span class='".$this->_style["first"]."'><a href='" . $this->mk_link($this->_href, 1, $append_url_params) . "' ".$this->get_additional_params($this->_href, 1)."><b>&#171;</b></a></span> <br />";
		}

		// If we are, example: on 22th page, we display links from 20 to 29. Before 20
		//        we write link "...", that user can jump on pages 10..19
		if ($sklop>0)
			if ($this->_orientation == 'horizontal')
				$links_pages .= "<span class='".$this->_style["previous"]."'><a href='" . $this->mk_link($this->_href, $sklop_links*($sklop), $append_url_params) . "' ".$this->get_additional_params($this->_href, $sklop_links*$sklop).">".$this->_navig["previous"]."</a></span> ";
			else
				$links_pages .= "<span class='".$this->_style["previous"]."'><a href='" . $this->mk_link($this->_href, $sklop_links*($sklop), $append_url_params) . "' ".$this->get_additional_params($this->_href, $sklop_links*$sklop)."><b>...</b></a></span> <br />";

		// we print links, example: from 20 to 29
		$links_od = ($sklop_links * $sklop + 1);
		$links_do = $sklop_links * ($sklop + 1) + 1;

		for ($i=$links_od; $i<$links_do; $i++) {
			// we build hrefs to individual pages
			if ($i==$current_page) {
				// we don't create link for current page
				if ($this->_orientation == 'horizontal')
					$links_pages .= "<span class='".$this->_style["current"]."'>" . $i . "</span> ";
				else
					$links_pages .= "<span class='".$this->_style["current"]."'>" . $i . "</span> <br />";

			} else {
				// we can't write more links to pages than we have possible pages
				if ($i<=$this->max_page)
					if ($this->_orientation == 'horizontal')
						$links_pages .= "<span class='".$this->_style["normal"]."'><a href='" . $this->mk_link($this->_href, $i, $append_url_params) . "' ".$this->get_additional_params($this->_href, $i).">" . $i . "</a></span> ";
					else // we build vertical pager
						$links_pages .= "<span class='".$this->_style["normal"]."'><a href='" . $this->mk_link($this->_href, $i, $append_url_params) . "' ".$this->get_additional_params($this->_href, $i).">" . $i . "</a></span> <br />";

			}
		}

		// If we are not on last page. we write link "..." for next assembly of pages
		if ($sklop_links*($sklop+1)<$this->max_page)
			if ($this->_orientation == 'horizontal')
				$links_pages .= "<span class='".$this->_style["next"]."'><a href='" . $this->mk_link($this->_href, $sklop_links * ($sklop+1) + 1, $append_url_params) . "' ".$this->get_additional_params($this->_href, $sklop_links * ($sklop+1) + 1).">".$this->_navig["next"]."</a></span> ";
			else
				$links_pages .= "<span class='".$this->_style["next"]."'><a href='" . $this->mk_link($this->_href, $sklop_links * ($sklop+1) + 1, $append_url_params) . "' ".$this->get_additional_params($this->_href, $sklop_links * ($sklop+1) + 1)."><b>...</b></a></span> <br />";

		//only if there are more pages, render "last" part of pager (>>)
		if ($this->max_page >= $links_do) {

			//if last = NB put there number of last page
			if ($this->_navig["last"] == "LP")
				$this->_navig["last"] = $this->max_page;

			// jump to last page
			$links_pages .= "<span class='".$this->_style["last"]."'><a href='" . $this->mk_link($this->_href, $this->max_page, $append_url_params) . "' ".$this->get_additional_params($this->_href, $this->max_page).">".$this->_navig["last"]."</a></span> ";
		}
                //link to go to next page (currentpage+1) added for catchup
                //search pagination
                if($this->enable_next_previous === true)
                {
                        //link to go to next page (currentpage+1)
        		if($current_page<$this->max_page)
        		{
        			$links_pages .= "<span class='".$this->_style["next"]."'><a href='" . $this->mk_link($this->_href, ($current_page+1), $append_url_params) . "' ".$this->get_additional_params($this->_href, $sklop_links*$sklop).">".$_['NEXT']."</a></span> ";
        		}
                }
                return $links_pages;

	}
	
	/**
	 * Returns string for additional HTML parameters inside of link to page
	 * We can add ajax handler in 'onclick' through this
	 */
	function get_additional_params($url, $page)
	{
		if (!$this->_additional_params)
			return "";

		$tmp = $this->_additional_params;

		$tmp = str_replace('##PAGE##', $page, $tmp);
		$tmp = str_replace('##URL##', $url, $tmp);
		$tmp = str_replace('##P_ALL_ITEMS##', $this->_all_items, $tmp);

		return $tmp;
	}

	/**
	 * Sets variable, which has HTMl for additional parameters
	 */
	function set_additional_params($additional_params)
	{
		$this->_additional_params = $additional_params;
	}

	/**
	 * Creates url link
	 */
	function mk_link($url, $page, $append_url_params)
	{
		// replaces ##page## , so that we can put PAGE number inside (i.e.  page_##page##.php)
		$url = str_replace('##PAGE##', $page, $url);
		// da, qs je ze, property "page" samo dodamo na konec z "&"

		// append URL parameters?
		if($append_url_params)
			return $url . "&amp;" . $this->_page_var . "=" . $page . "&amp;" . $this->_all_items_var . "=" . $this->_all_items . $this->_href_post . $this->_anchor;
		else
			return $url . $this->_anchor;
	}


} // <-- class end





//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~





/**
 * This class works with templates.
 * Look at help file "template.class.html"
 *
 */

class cls_Template
{
	var $_file_name = "";
	var $_file_fs_name = "";

	// variable contains entire template
	var $_template = "";

	// where the directory of project is (/www/)
	var $_template_path = "";

	// are header and footer added to page
	var $_add_hf = 'auto';

	//
	var $_print = "";

	// name of header file
	var $_header_name = "";

	// name of footer file
	var $_footer_name = "";

	// name of header file, which is used at priting
	var $_header_name_print = "";

	// name of footer file, which is used at printing
	var $_footer_name_print = "";

	// name of menu file ( in directory menu/ in templates)
	var $_menu = "";

	// which part in left menu is selected
	var $_left_menu_selected = "";

	// which part in upper menu is selected
	var $_top_menu_selected = "";

	// we need this to display different backgrounds in listing tables
	//        It's just for internal use, it's used and changed by
	//        function row_class();
	var $_is_td_even = array();

	// If the varialble is TRUE, template is compiled in any case
	var $_force_compile = FALSE;

	// which file has language file (that we check, if it changed)
	var $_lang_file = "";

	// where to save compiled files (which dir inside of template dir. Default is compiled/$APP_LANG)
	var $_save_folder = "";

	// checking of boxes
	var $_boxes_parsed = array();
	var $_boxes_added = false;

	// do we clean comments
	var $_comments_strip = TRUE;

	// css files
	var $_css_files = array();

 	// array of repeater objects
 	var $_pile = '';
 
 	// automatically collect repeaters on pile
 	var $_auto_pile = true;
 
 	// input template
 	var $_blank = '';
 
 	// indicating whether current instance is a repeater
 	var $_is_repeater = false;
 
 	// first tag in a repeater
 	var $_first_tag = null;

	/**
	 * Inicialises the class.
	 */
	function cls_Template($template_path='', $filename='' ) {
		global $BASE_DIR, $APP_LANG, $COMMENTS_STRIP, $APP_TYPE;

		$this->_lang_file = $BASE_DIR . "/local/" . strtolower($APP_LANG) . "/inc/" . $APP_TYPE . ".inc";
		$this->_save_folder = "compiled/" . $APP_LANG. "/". $APP_TYPE;

		// sets root path
		if ($template_path)
			$this->_template_path = $template_path;
		else
			$this->_template_path = $this->guess_template_path();

		// we add "/", if it's not there
		if ($this->_template_path)
			if (substr($this->_template_path,strlen($this->_template_path) - 1) != "/")
				$this->_template_path .= "/";

		// sets name of template file, if it's specified
		if ($filename)
			$this->_file_name = $filename;
		else
			$this->_file_name = $this->guess_template_name();

		$this->_template = "";
		$this->_menu = "";
		$this->_left_menu_selected = "";
		$this->_top_menu_selected = "";
		$this->_add_hf = 'auto';
		$this->_header_name = "header.tpl";
		$this->_footer_name = "footer.tpl";
		$this->_header_name_print = "header_print.tpl";
		$this->_footer_name_print = "footer_print.tpl";
		$this->_print = FALSE;

		// do not strip comments on dev sites
		if ( $COMMENTS_STRIP === FALSE )
			$this->_comments_strip = FALSE;
		return $this;
	}

	/**
	 * Function sets name of template
	 */
	function filename ($val) {
		if (trim($val)!="")
			$this->_file_name = $val;

		return $this;
	}

	/**
	 * Function sets template, that it's ready for printing
	 */
	function for_print ($val)
	{
	       if (!is_numeric($val) ) return false;
		$this->_print = ( ((int)$val) != 0 );

		return $this;
	}

	/**
	 * Function sets if header and footer are added
	 */
	function add_hf ($val) {
		$this->_add_hf = $val;

		return $this;
	}

	/**
	 * Function sets name of menu, which we will display
	 */
	function menu ($menu_file = "", $left_menu_selected = "", $top_menu_selected = "") {
		$this->_menu = $menu_file;
		$this->_left_menu_selected = $left_menu_selected;
		$this->_top_menu_selected = $top_menu_selected;

		return $this;
	}

	/**
	 * Function sets name of header and footer file,
	 *        if we won't use default files.
	 */
	function header_footer($header, $footer) {
		$this->_header_name = $header;
		$this->_footer_name = $footer;
		$this->_add_hf = TRUE;

		return $this;
	}

	/**
	 * Funkction sets name of  header and footer file for PRINTING,
	 *        if we want use default files.
	 */
	function header_footer_print($header_print, $footer_print) {
		$this->_header_name_print = $header_print;
		$this->_footer_name_print = $footer_print;

		return $this;
	}

	/**
	 * Funkction opens template
	 */
	function open() {
		$tmp = "";

		// if file_name is not send, we try to find template's name
		if (!$this->_file_name)
			$this->_file_name = $this->guess_template_name();

		$this->_file_fs_name = $this->_template_path . '/' . $this->_file_name;

		// we open and read template
		$tmp = $this->get_source($this->_file_fs_name);

		// we add header and footer
		if ( $this->_add_hf === 'auto' )
			$this->_add_hf = (strpos($tmp, '##HEADER_FILE##') !== FALSE);

		if ($this->_add_hf)
			$this->_template = $this->add_header_footer($tmp);
		else
			$this->_template = $tmp;

		if ($this->_menu) $this->_template = $this->add_menu();

		$this->_template = $this->remove_sec($this->_template);

		//replace with local language
		$this->_template = $this->replace_local_lang($this->_template);

		// we throw out all between ##NO_PRINT_START## and ##NO_PRINT_END## tags,
		//        if we will print
		if ($this->_print) {
			while ($this->tag_exists("NO_PRINT_START")) $this->repeater("NO_PRINT");
		}

		// add custom fields
		$this->custom_fields();

		// clean comments
		if ( $this->_comments_strip )
			$this->_template = $this->comments_strip( $this->_template );

		return $this;
	}

	/**
	 * Function strips comments out of template
	 */
	function comments_strip( $tpl ) {
		$res = eregi_replace("<!--[^>;=/]*-->","",$tpl);
		$res = preg_replace("/[\t\r\n\s]*$/m","", $res);
		$res = preg_replace("/[\t ]+/i"," ",$res);
		//$res = preg_replace("/[\n]+/i"," ",$res); //put whole source in one line
		return $res;
	}

	/**
	  * Function that displays ONLY ##SEC## fields and strips everything else
	 */
	function display_sec()
	{
		$secs = "";
		preg_match_all("/(##SEC\([0-9]\)##)(.*)\\1/Us",$this->_template,$result);
		foreach ($result[2] as $sec)
		{
			$secs .= $sec;
		}

		return $secs;

	}
	/**
	 * Funkction deletes ##SEC## fields, which are only for backend
	 */
	function remove_sec( $template ) {

		global $BACKEND;
		if ($BACKEND)
			return $template;

		while (preg_match("/(##SEC\((.*)\)##)(.*)\\1/Us",$template,$ar)) {
			if ($BACKEND) {
			/*
				if ($ar[2]=="F") {
					$template = str_replace($ar[0],"",$template);
				} else {
					$template = str_replace($ar[1],"",$template);
				}*/
			} else {
				if ($ar[2]=="F") {
					$template = str_replace($ar[1],"",$template);
				} else {
					$template = str_replace($ar[0],"",$template);
				}
			}
		}

		return $template;
	}

	/**
	 * Function writes into template custom fields, whick are neccesary just for rhis aplication
	 */
	function custom_fields() {
		if (!$this->add_custom_fields) return TRUE;
	}

	/**
	 * Function adds menu into template
	 */
	function add_menu() {
		$tmp="";

		$tmp = str_replace("##PAGE_MENU##", $this->get_source($this->_template_path . $this->_menu) , $this->_template);
		$tmp = str_replace("##LEFT_MENU_SELECTED##", $this->_left_menu_selected, $tmp);

		return $tmp;
	}

	/**
	 * Funkcija prints template
	 */
	function write() {

		// add css files
		$this->build_css();

		// we clear other tags remained
		$this->clear();

		echo $this->_template;
	}

	/**
	 * Function clears all other tags remained and returns entire template.
	 */
	function get($clear = '#$')
	{
		// copy concatenated repeater template from _pile into _template
		if($this->_is_repeater && (0 < strlen($this->_pile)))
		{
			// store last html
			$tmp_template = $this->_template;

			// prepare output string
			$this->_template = $this->_pile;

			// append last html
			if($this->_auto_pile)
				$this->_template .= $tmp_template;

			// purge the pile
			$this->_pile = '';

			// reset first tag
			$this->_first_tag = null;
		}

		$this->build_css();

		// for old-version compatibility
		if ($clear === true) {
			$clear='#$';
		}

		// we clear other tags remained
		if ($clear) $this->clear($clear);


		return $this->_template;
	}

	/**
	 * Function prints template on way it is. Specially
	 *        usefull for debug!
	 */
	function dump() {
		echo $this->_template;
		die("");
	}

	/**
	 * Funkction replace tag with data
	 */
	function replace($tag, $val)
	{
		if($this->_is_repeater && $this->_auto_pile)
		{
			// add last processed repeater template to pile if in new loop
			if($this->_first_tag == $tag)
				$this->add();
		
			// set first tag
			if(!$this->_first_tag)
				$this->_first_tag = $tag;
		}

		if (!isset($val))
			$val="";

		// if the tag is array we implode it
		if (count($val)>1) $val = implode("", $val);

		// tag has to be framed inside of "##"
		if (strpos($tag,"##")===false)
			$tag="##" . $tag . "##";

		if (strpos($tag,"*")!==false)
			$this->_template = $this->replace_all($this->_template,$tag,$val);
		else
			$this->_template = str_replace($tag,$val,$this->_template);

		return $this;
	}

	/**
	 * Returns TRUE, id tag exists inside of template.
	 */
	function tag_exists($tag) {
		// tag has to be framed inside of "##"
		if (strpos($tag,"##")===false) $tag="##" . $tag . "##";

		if (strpos($this->_template, $tag)===false)
			return false;
		else
			return true;
	}

	/**
	 * Returns TRUE, id tag exists inside of row of template.
	 */
	function row_tag_exists($tag,$row) {
		// tag has to be framed inside of "##"
		if (strpos($tag,"##")===false) $tag="##" . $tag . "##";

		if (strpos($row, $tag)===false)
			return false;
		else
			return true;
	}

	/**
	 * Function replaces all instances of $taga with value $val in $text
	 */
	function replace_all($text, $tag, $val) {
		$tag_part = "";
		$whole_tag = "";
		$pos_begin = 0;
		$pos_end = 0;

		$tag_part = substr($tag,0,strpos($tag,"*")-1);

		while (strpos($text,$tag_part)!==false) {
			$pos_begin = strpos($text,$tag_part);
			$pos_end = strpos($text, "##", $pos_begin + 1) + 2;
			$whole_tag = substr($text, $pos_begin, $pos_end - $pos_begin );

			$text = str_replace($whole_tag,$val,$text);
		}

		return $text;
	}

	/**
	 * Function replaces in ROW tag with data
	 */
	function row($tag, $val, &$row) {

		if (!isset($val))
			$val="";

		// tag has to be framed inside of "##"
		if (strpos($tag,"##")===false)
			$tag="##" . $tag . "##";

		if (strpos($tag,"*")!==false)
			$row = $this->replace_all($row,$tag,$val);
		else
			$row = str_replace($tag, $val, $row);

		return $this;
	}

	/**
	 * Function adds row ($row) in array of rows ($table)
	 */
	function row_add(&$table, $row) {
		$table[] = $row;
		return true;
	}

	/**
	 * Function writes in variable ##TD_CLASS## in ROW class name for table.
	 * We manage with internal varible is_td_even in class. Everytime, when
	 *  this method is called, this varible changes.
	 * If the is_td_even=true, we write in ##TD_CLASS## 'even', else we write 'odd'.
	 * $idx is index, if we have more tables in table and every table has its own index for odd - even.
	 */
	function row_class(&$row, $idx = 0) {

		if ($this->_is_td_even[$idx])
			$this->row("TD_CLASS", "td_odd", $row);
		else
			$this->row("TD_CLASS", "td_even", $row);

		$this->_is_td_even[$idx] = !$this->_is_td_even[$idx];

		return true;
	}

	/**
	 * Function read content of file $file_name and
	 *      replaces tag $tag in template with content.
	 */
	function add_sub($tag, $file_name) {

		$file_name = $this->_template_path . $file_name;
		$tmp = $this->get_source($file_name);

		return $this->replace($tag, $tmp);
	}

	/**
	 * Function returns text between ##$tag_START## and ##$tag_END## tags and replaces it in template
	 *      with ##$tag##.
	 */
	function repeater($tag) {

		$tag = str_replace("##", "", $tag);

		$start_tag = "##" . $tag . "_START##";
		$end_tag = "##" . $tag . "_END##";

		$start_pos = strpos($this->_template,$start_tag);
		$end_pos = strpos($this->_template,$end_tag);

		// if there is no starting and finishing tag, we go out of function
		if (($start_pos===false) || ($end_pos===false)) return "";

		$tmp = substr($this->_template, $start_pos + strlen($start_tag), $end_pos - $start_pos - strlen($start_tag));

		if ($start_pos>0) $start_pos--;

		$this->_template = substr($this->_template,0,$start_pos+1) .
					"##" . $tag . "##" .
					substr($this->_template,$end_pos + strlen($end_tag));

		return $tmp;
	}

	/**
	 * Function returns new class, having text between START and END as content.
	 *
	 * @author Matej Balantič <matej@balantic.si>
	 * @param string $tag
	 */
	function repeat($tag, $auto_pile = true) {

		$tag = str_replace("##", "", $tag);

		$start_tag = "##" . $tag . "_START##";
		$end_tag = "##" . $tag . "_END##";

		$start_pos = strpos($this->_template,$start_tag);
		$end_pos = strpos($this->_template,$end_tag);

		// if there is no starting and finishing tag, we go out of function
		if (($start_pos===false) || ($end_pos===false)) return "";

		$tmp = substr($this->_template, $start_pos + strlen($start_tag), $end_pos - $start_pos - strlen($start_tag));

		if ($start_pos>0) $start_pos--;

		$this->_template = substr($this->_template,0,$start_pos+1) .
					"##" . $tag . "##" .
					substr($this->_template,$end_pos + strlen($end_tag));

		$new_class = new cls_template();
		$new_class->_template = $tmp;

		// stash blank template
 		$new_class->_blank = $new_class->_template;
 		$new_class->_is_repeater = true;
 		$new_class->_auto_pile = $auto_pile;

		return $new_class;
	}

	/**
	 *  Function replaces intermmediate part in row ($row) between $tag_START in $tag_END tags and replaces it
	 *      with $tag keyword.
	 *
	 */
	function row_repeater($tag, &$row) {

		$tag = str_replace("##", "", $tag);

		$start_tag = "##" . $tag . "_START##";
		$end_tag = "##" . $tag . "_END##";

		$start_pos = strpos($row,$start_tag);
		$end_pos = strpos($row,$end_tag);


		if (($start_pos===false) || ($end_pos===false)) return "";

		$tmp = substr($row, $start_pos + strlen($start_tag), $end_pos - $start_pos - strlen($start_tag));

		if ($start_pos>0) $start_pos--;

		$row = substr($row,0,$start_pos+1) .
					"##" . $tag . "##" .
					substr($row,$end_pos + strlen($end_tag));

		return $tmp;
	}

	/**
	 * Function puts asociative array in VARIABLE $row
	 * Key is name of tag, value is value, which is written there where the tag is.
	 */
	function row_array($array, &$row) {

		if (!$array)
			return;

		foreach ($array AS $key => $val)
			$this->row('##' . $key . '##', $val, $row);

		return $this;
	}

	/**
	 * Function writes asociative array into TEMPLATE
	 * Key is name of tag, value is value, which is written there where the tag is.
	 */
	function replace_array($array) {

		if (!$array)
			return;

		foreach ($array AS $key => $val)
			$this->replace('##' . $key . '##', $val);

		return $this;
	}

	/**
	 * Function clears all other tags remained.
	 */
	function clear($clear='#$') {
	if (preg_match('/#/',$clear)) {

			if (strpos($this->_template,"##TEST_BLANK##")!==false) {
			    while (preg_match("/(##TEST_BLANK##(.*)##IS_BLANK##(.*)##IS_BLANK##(.*)##TEST_BLANK##)/Us",$this->_template,$ar)) {
				if (trim($ar[3])=="") {
				    $this->_template = str_replace($ar[0],"",$this->_template);
				} else {
				    $this->_template = str_replace($ar[0],$ar[2].$ar[3].$ar[4],$this->_template);
				}
			    }
		}

		$this->_template = ereg_replace("##[^##]+##","",$this->_template);
		}

		if (preg_match('/\$/',$clear)) {
			$this->_template = ereg_replace('\$\$[^(][^\$\$]+\$\$',"",$this->_template);
		}
	return $this;
	}

	/**
	 * It removes all tags remained from row of table.
	 */
	function row_clear(&$row,$clear='#$') {
		if (preg_match('/#/',$clear)) {
			$row = ereg_replace("##[^##]+##","",$row);
		}

		if (preg_match('/\$/',$clear)) {
			$row = ereg_replace('\$\$[^\$\$]+\$\$',"",$row);
		}

		return $this;
	}

	/**
	 * Function adds header in footer to template of page
	 */
	function add_header_footer($source) {

		$this->check_hf_in_template( $source );

		// we add ordinary header in footer
		$tmp = $this->get_source($this->_template_path . $this->_header_name) .
				"\n" . "<!-- DATA START  -->" .
				$source .
				"\n" . "<!-- DATA END -->" .
				$this->get_source($this->_template_path . $this->_footer_name);

		
		if (strstr($tmp,"##IF_WEB_TITLE##")) {
			$ex = explode("##IF_WEB_TITLE##",$tmp);
			$w_title=$ex[1];
			$tmp=str_replace("##IF_WEB_TITLE##".$w_title."##IF_WEB_TITLE##","",$tmp);
			$tmp=str_replace("##PAGE_TITLE##",$w_title,$tmp);
		}
		
		return $tmp;
	}

	
	/**
	 * Function checks, if the header in footer are written in template
	 */
	function check_hf_in_template( &$template ) {

		if (strstr($template,"##HEADER_FILE##")) {
			$ex = explode("##HEADER_FILE##",$template);
			$this->_header_name = $ex[1];
			$template=str_replace("##HEADER_FILE##".$ex[1]."##HEADER_FILE##","",$template);

			if ( strpos($this->_header_name, '/') === FALSE )
				$this->_header_name = dirname($this->_file_name) . "/" . $this->_header_name;
		}

		if (strstr($template,"##FOOTER_FILE##")) {
			$ex = explode("##FOOTER_FILE##",$template);
			$this->_footer_name = $ex[1];
			$template=str_replace("##FOOTER_FILE##".$ex[1]."##FOOTER_FILE##","",$template);

			if ( strpos($this->_footer_name, '/') === FALSE )
				$this->_footer_name = dirname($this->_file_name) . "/" . $this->_footer_name;
		}

		return TRUE;
	}

	/**
	 * Function opens template
	 * It opens compiled source, if it has to
	 */
	function get_source($file_name)
	{
		global $BACKEND, $APP_LANG;

		//check if template exists on specific APP_LANG location
		$specific_file = str_replace("core/presentation","local/".strtolower($APP_LANG)."/presentation",$file_name);
		if (file_exists($specific_file))
			$file_name = $specific_file;

		$template_source = $this->get_compiled( $file_name );

		if ( !file_exists($file_name) )
			trigger_error("TEMPLATE: Cannot find file: ".$file_name, E_USER_NOTICE);

		return $template_source;
	}


	/**
	 * Fuctions tries to find name of template's file
	 * It takes for name of template's file: name_of_php_script.tpl
	 */
	function guess_template_name() {
		// if file is from /bin/, we remove /bin/ and change .php to .tpl
		if ( strpos($_SERVER["SCRIPT_NAME"], '/bin/') !== FALSE )
			return str_replace('/bin/', '', str_replace('.php', '.tpl', $_SERVER["SCRIPT_NAME"]) );

		return basename($_SERVER["SCRIPT_NAME"], ".php") . ".tpl";
	}

	/**
	 * Function tries to find out name of wwwroot directory
	 */
	function guess_template_path() {
		global $TEMPLATE_DIR, $FRONTEND_DIR, $TEMPLATE_B2_DIR;

		if ( strpos($_SERVER["DOCUMENT_ROOT"],'backend-2') !== FALSE )
			return $TEMPLATE_B2_DIR;
		else if ( strpos($_SERVER["DOCUMENT_ROOT"],'backend') !== FALSE )
			return $TEMPLATE_B2_DIR;
		else if ( strpos($_SERVER["DOCUMENT_ROOT"],'generirane') !== FALSE )
			return $TEMPLATE_DIR . $FRONTEND_DIR;
		else if ( strpos($_SERVER["DOCUMENT_ROOT"],'staticne') !== FALSE )
			return $TEMPLATE_DIR . $FRONTEND_DIR;
		else if ( strpos($_SERVER["DOCUMENT_ROOT"],'generate') !== FALSE )
			return $TEMPLATE_DIR . $FRONTEND_DIR;

		return $TEMPLATE_DIR . $FRONTEND_DIR;
	}


	/**
	 * Function compiles source template with language definitions
	 * ! Just BE files have translations
	 */
	function compile($source_file, $dest_file) {
	    global $_;

	    global $BACKEND, $CHECK_TRANSLATION;

	    $template_source = file_get_contents( $source_file );
	    preg_match_all( '/\$\$([^(].*)\$\$/Uis', $template_source, $keys );

	    if ( is_array($keys[1]) ) {

		    $search = array('\'', '\"');
		    $replace = array('&#39;', '&quot;');

		    foreach ($keys[1] as $key) {
			    if ( !$_[$key] )
				    $template_source = str_replace("$$".$key."$$", str_replace($search, $replace, $key), $template_source);
			    else {
				    $template_source = str_replace("$$".$key."$$", str_replace($search, $replace, $_[$key]), $template_source);

				    //if backend and do_check ->  update status for translation
/*
				    if ($BACKEND && $CHECK_TRANSLATION) {
					$dbTemp = new DB_Sql('','translations');
					$sql = "UPDATE word SET last_used = current_timestamp WHERE var_name = '".$key."' AND site_id = 2 LIMIT 1";
					$dbTemp->query($sql);
				    }
*/
			    }
		    }
	    }

	    // save compiled file
	    $this->save_compiled( $dest_file, $template_source );

	    return $template_source;
	}
	function t_compile($source_file, $dest_file) { return compile($source_file, $dest_file); }

	/**
	 * Saves compiled source to disk in creates directory structure
	 */
	function save_compiled( $dest_file, &$template_source ) {

		$dest_file = str_replace("//", "/", $dest_file);

		if ( !file_exists(dirname($dest_file)) )
			$this->mk_dir( $dest_file );

		$fp=fopen($dest_file, "w");

		if ($fp != false ) {
			fwrite($fp, $template_source);
			fclose($fp);
			chmod($dest_file, 0770);
		}

		return TRUE;
	}

	/**
	 * Creates apecified directory structure
	 */
	function mk_dir( $dir ) {

		$offset = 1;

		while ( ($where=strpos($dir, "/", $offset)) !== false) {

			if (!file_exists(substr($dir, 0, $where))) {

				if ( !mkdir(substr($dir, 0, $where), 0770) ) {
					trigger_error("TEMPLATE: Cannot write to: " . substr($dir, 0, $where), E_USER_ERROR);
				}
			}
			$offset = $where + 1;
		}

		return TRUE;
	}


	/**
	 * We get compiled template (in current language. If it's not compiled yet, we compile it at first)
	 */
	function get_compiled( $source )
	{
		// we check if compiled template already exists..
		$compiled = str_replace("templates/", "templates/" . $this->_save_folder . "/", $source);
		$compiled = str_replace("_keys/", "", $compiled);

		// if compiled version doesn't exist .. we build it
		if ($this->_force_compile)
			return $this->compile($source, $compiled);

		// if compiled version doesn't exist yet, or has size=0 .. we build it
		if (!file_exists($compiled) || !(int)@filesize($compiled))
			return $this->compile($source, $compiled);

		// is source newer then compiled?
		if (filemtime($source) > filemtime($compiled))
			return $this->compile($source, $compiled);

		// was translations for this language updated?
		if (filemtime($this->_lang_file) > filemtime($compiled))
			return $this->compile($source, $compiled);

		return @file_get_contents( $compiled );
	}


	/**
	 * Function adds a CSS link to header
	 *
	 */
	function add_css( $url )
	{
		$this->_css_files[] = $url;

		return $this;
	}

	function build_css()
	{
		if (!$this->tag_exists("INCLUDE_CSS"))
			return false;

		$files = '';
		foreach ($this->_css_files as $file)
		{
			$files .= '<link href="'.$file.'" type="text/css" rel="stylesheet" />'."\n";
		}
		$this->replace("INCLUDE_CSS", $files);

		return $this;
	}

	/**
	 * method replaces APP_LANG_case TAGS in templates
	 * @return string (template)
	 * @author benjamin.povirk@pop-tv.si, anze.znidarsic@pop-tv.si 10.6.08
	 */
	function replace_local_lang($template) {
		global $APP_LANG;
		$template = str_replace("##APP_LANG_LOWER##",strtolower($APP_LANG), $template);
		$template = str_replace("##APP_LANG_UPPER##",strtoupper($APP_LANG), $template);
		return $template;

	}

	/**
	 * Concatenate current processed repeater template
	 * with existing template and reset current template
	 * @author Mitja Ursic
	 */
	function add()
	{
		$this->_pile .= $this->_template;
		$this->_template = $this->_blank;
	}

	/**
	 * Set _template from source
	 * @param string or object source of template string
	 * @author Mitja Ursic
	 */
	function set_t($source)
	{
 		if(is_object($source))
 			$this->_template = $source->_template;

 		if(is_string($source))
 			$this->_template = $source;
	}
} // <-- end class
?>
