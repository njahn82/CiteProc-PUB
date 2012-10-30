<?php
/**
 *   CiteProc-PHP
 *
 *   Copyright (C) 2010 - 2011  Ron Jerome, all rights reserved
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

class citeproc {
  public    $bibliography;
  public    $citation;
  public    $style;
  protected $macros;
  private   $info;
  protected $locale;
  protected $style_locale;
  private   $mapper = NULL;

  function __construct($csl = NULL, $lang = 'en') {
    if ($csl) {
      $this->init($csl, $lang);
    }
  }

  function init($csl, $lang) {
    // define field values appropriate to your data in the csl_mapper class and un-comment the next line.
    $this->mapper = new csl_mapper();

    $csl_doc = new DOMDocument();

    if ($csl_doc->loadXML($csl)) {

      $style_nodes = $csl_doc->getElementsByTagName('style');
      if ($style_nodes) {
        foreach ($style_nodes as $style) {
          $this->style = new csl_style($style);
        }
      }

      $info_nodes = $csl_doc->getElementsByTagName('info');
      if ($info_nodes) {
        foreach ($info_nodes as $info) {
          $this->info = new csl_info($info);
        }
      }

      $this->locale = new csl_locale($lang);
      $this->locale->set_style_locale($csl_doc);


      $macro_nodes = $csl_doc->getElementsByTagName('macro');
      if ($macro_nodes) {
        $this->macros = new csl_macros($macro_nodes, $this);
      }

      $citation_nodes = $csl_doc->getElementsByTagName('citation');
      foreach ($citation_nodes as $citation) {
        $this->citation = new csl_citation($citation, $this);
      }

      $bibliography_nodes = $csl_doc->getElementsByTagName('bibliography');
      foreach ($bibliography_nodes as $bibliography) {
        $this->bibliography = new csl_bibliography($bibliography, $this);
      }
    }
  }
  function render($data, $mode = NULL) {
    $text = '';
    //switch ($mode) {
    //  case 'citation':
    //    $text .=  (isset($this->citation))? $this->citation->render($data) : '';
    //    break;
    //  case  'bibliography':
    //  default:
        $text .=  (isset($this->bibliography))? $this->bibliography->render($data) : '';
    //    break;
    //}
    return $text;
  }

  function render_macro($name, $data, $mode) {
    return $this->macros->render_macro($name, $data, $mode);
  }

  function get_locale($type, $arg1, $arg2 = NULL, $arg3 = NULL) {
    return $this->locale->get_locale($type, $arg1, $arg2, $arg3);
  }

  function map_field($field) {
    if ($this->mapper) {
      return $this->mapper->map_field($field);
    }
    return ($field);
  }
  function map_type($field) {
    if ($this->mapper) {
      return $this->mapper->map_type($field);
    }
    return ($field);
  }
}

class csl_factory {
  public static function create($dom_node, $citeproc = NULL) {
    $class_name =  'csl_' . str_replace('-', '_', $dom_node->nodeName);
    if (class_exists($class_name)) {
      return new $class_name($dom_node, $citeproc);
    }
    else {
      return NULL;
    }
  }
}

class csl_collection {
  protected  $elements = array();

  function add_element($elem) {
    if (isset($elem)) $this->elements[] = $elem;
  }

  function render($data, $mode = NULL) {}

  function format($text) {return $text;}

}

class csl_element extends csl_collection {
  protected $attributes = array();
  protected $citeproc;

  function __construct($dom_node = NULL, $citeproc = NULL) {

    $this->citeproc   = &$citeproc;
    $this->set_attributes($dom_node);
    $this->init($dom_node, $citeproc);

  }

  function init($dom_node, $citeproc) {
    if (!$dom_node) return;

    foreach ($dom_node->childNodes as $node) {
      if ($node->nodeType == 1) {
        $this->add_element(csl_factory::create($node, $citeproc));
      }
    }
  }

  function __set($name, $value) {
    $this->attributes[$name] = $value;
  }

  function __isset($name) {
    return isset($this->attributes[$name]);
  }

  function __unset($name) {
    unset($this->attributes[$name]);
  }

  function &__get($name = NULL) {
    $null = NULL;
    if (array_key_exists($name, $this->attributes)) {
      return $this->attributes[$name];
    }
    return $null;

  }

  function set_attributes($dom_node) {
    $att = array();
    $element_name = $dom_node->nodeName;
    if (isset($dom_node->attributes->length)) {
      for ($i=0; $i < $dom_node->attributes->length; $i++) {
        $value = $dom_node->attributes->item($i)->value;
        $name  = str_replace(' ', '_', $dom_node->attributes->item($i)->name);
        if ($name == 'type' ) {
          $value = $this->citeproc->map_type($value);
        }

        if (($name == 'variable'  || $name == 'is-numeric') && $element_name != 'label') {
          $value = $this->citeproc->map_field($value);
        }
        $this->{$name}  = $value;
      }
    }
  }

  function get_attributes() {
      return $this->attributes;
  }

  function get_hier_attributes() {
    $hier_attr = array();
    $hier_names = array('and', 'delimiter-precedes-last', 'et-al-min', 'et-al-use-first',
                        'et-al-subsequent-min', 'et-al-subsequent-use-first', 'initialize-with',
                        'name-as-sort-order', 'sort-separator', 'name-form', 'name-delimiter',
                        'names-delimiter');
    foreach ($hier_names as $name) {
      if (isset($this->attributes[$name])) {
        $hier_attr[$name] = $this->attributes[$name];
      }
    }
    return $hier_attr;
  }

  function name($name = NULL) {
    if ($name) {
      $this->name = $name;
    }
    else {
      return str_replace(' ', '_', $this->name);
    }
  }

}

class csl_rendering_element extends csl_element {

  function render($data, $mode = NULL) {
    $text = '';
    $text_parts = array();

    $delim = $this->delimiter;
    foreach ($this->elements as $element) {
      $text_parts[] = $element->render($data, $mode);
    }
    $text = implode($delim, $text_parts); // insert the delimiter if supplied.

    return $this->format($text);
  }

}

class csl_format extends csl_rendering_element {
  protected $no_op;
  protected $format;

  function __construct($dom_node = NULL, $citeproc = NULL) {
    parent::__construct($dom_node, $citeproc);
    $this->init_formatting();
  }

  function init_formatting() {
    $this->no_op = TRUE;
    $this->format  = '';
    if (isset($this->quotes)) {
      $this->quotes = array();
      $this->quotes['punctuation-in-quote'] = $this->citeproc->get_locale('style_option', 'punctuation-in-quote');
      $this->quotes['open-quote'] = $this->citeproc->get_locale('term', 'open-quote');
      $this->quotes['close-quote'] = $this->citeproc->get_locale('term', 'close-quote');
      $this->quotes['open-inner-quote'] = $this->citeproc->get_locale('term', 'open-inner-quote');
      $this->quotes['close-inner-quote'] = $this->citeproc->get_locale('term', 'close-inner-quote');
      $this->no_op = FALSE;
    }
    if (isset($this->{'prefix'})) $this->no_op = FALSE;
    if (isset($this->{'suffix'})) $this->no_op = FALSE;
    if (isset($this->{'display'})) $this->no_op = FALSE;

    $this->format .= (isset($this->{'font-style'}))      ? 'font-style: ' . $this->{'font-style'} . ';' : '';
    $this->format .= (isset($this->{'font-family'}))     ? 'font-family: ' . $this->{'font-family'} . ';' : '';
    $this->format .= (isset($this->{'font-weight'}))     ? 'font-weight: ' . $this->{'font-weight'} . ';' : '';
    $this->format .= (isset($this->{'font-variant'}))    ? 'font-variant: ' . $this->{'font-variant'} . ';' : '';
    $this->format .= (isset($this->{'text-decoration'})) ? 'text-decoration: ' . $this->{'text-decoration'} . ';' : '';
    $this->format .= (isset($this->{'vertical-align'}))  ? 'vertical-align: ' . $this->{'vertical-align'} . ';' : '';
    // $this->format .= (isset($this->{'display'})  && $this->{'display'}  == 'indent')  ? 'padding-left: 25px;' : '';

    if (isset($this->{'text-case'}) ||
        !empty($this->format) ||
        !empty($this->span_class) ||
        !empty($this->div_class)) {
          $this->no_op = FALSE;
        }

  }

  function format($text) {

    if (empty($text) || $this->no_op) return $text;
    if (isset($this->{'text-case'})) {
      switch ($this->{'text-case'}) {
        case 'uppercase':
          $text = mb_strtoupper($text);
          break;
        case 'lowercase':
          $text = mb_strtolower($text);
          break;
        case 'capitalize-all':
        case 'title':
          $text = mb_convert_case($text, MB_CASE_TITLE);
          break;
        case 'capitalize-first':
          $chr1 = mb_strtoupper(mb_substr($text, 0, 1));
          $text = $chr1 . mb_substr($text, 1);
          break;
      }
    }

    $prefix = $this->prefix . $this->quotes['open-quote'];
    $suffix = $this->suffix;
    if ($this->quotes['close-quote'] && !empty($suffix) && $this->quotes['punctuation-in-quote']) {
      if (strpos($suffix, '.') !== FALSE || strpos($suffix, ',') !== FALSE) {
        $suffix =  $suffix . $this->quotes['close-quote'];
      }
    }
    elseif ($this->quotes['close-quote']) {
      $suffix =  $this->quotes['close-quote'] . $suffix;
    }
    if (!empty($suffix)) { // gaurd against repeaded suffixes...
      $no_tags = strip_tags($text);
      if (strlen($no_tags) && ($no_tags[(strlen($no_tags) - 1)] == $suffix[0]) ) {
        $suffix = substr($suffix, 1);
      }
    }

    if (!empty($this->format) || !empty($this->span_class)) {
      $style = (!empty($this->format)) ? 'style="' . $this->format . '" ' : '';
      $class = (!empty($this->span_class)) ? 'class="' . $this->span_class . '"' : '';
      $text = '<span ' . $class . $style . '>' . $text . '</span>';
    }
    $div_class = $div_style = '';
    if (!empty($this->div_class)) {
       $div_class = (!empty($this->div_class)) ? 'class="' . $this->div_class . '"' : '';
    }
    if ($this->display  == 'indent') {
       $div_style =  'style="text-indent: 0px; padding-left: 45px;"';
    }
    if ($div_class || $div_style) {
      return '<div ' . $div_class . $div_style . '>' . $prefix . $text . $suffix . '</div>';
    }

    return $prefix . $text . $suffix;
  }

}

class csl_info {
  public $title;
  public $id;
  public $authors = array();
  public $links = array();

  function __construct($dom_node) {
    $name = array();
    foreach ($dom_node->childNodes as $node) {
      if ($node->nodeType == 1) {
        switch ($node->nodeName) {
          case 'author':
          case 'contributor':
            foreach ($node->childNodes as $authnode) {
              if ($node->nodeType == 1) {
                $name[$authnode->nodeName] = $authnode->nodeValue;
              }
            }
            $this->authors[] = $name;
            break;
          case 'link':
            foreach ($node->attributes as $attribute) {
              $this->links[] = $attribute->value;
            }
            break;
          default:
            $this->{$node->nodeName} = $node->nodeValue;
        }
      }
    }

  }
}

class csl_terms {

}

class csl_name extends csl_format {
  private $name_parts = array();
  private $attr_init = FALSE;

  function __construct($dom_node, $citeproc = NULL) {

    $tags = $dom_node->getElementsByTagName('name-part');
    if ($tags) {
      foreach ($tags as $tag) {
        $name_part = $tag->getAttribute('name');
        $tag->removeAttribute('name');
        for ($i=0; $i < $tag->attributes->length; $i++) {
          $value = $tag->attributes->item($i)->value;
          $name  = str_replace(' ', '_', $tag->attributes->item($i)->name);
          $this->name_parts[$name_part][$name]  = $value;
        }
      }
    }

    parent::__construct($dom_node, $citeproc);
  }

  function init_formatting() {
    $this->no_op = array();
    $this->format = array();
    $this->base = $this->get_attributes();
    $this->format['base']  = '';
    $this->format['family']  = '';
    $this->format['given']  = '';
    
    
    
    // NEW !!!
    $this->format['literal'] = '';
    // NEW END !!!
    
    
    
    $this->no_op['base'] = TRUE;
    $this->no_op['family'] = TRUE;
    $this->no_op['given'] = TRUE;

    if (isset($this->prefix)) {
      $this->no_op['base'] = FALSE;
    }
    if (isset($this->suffix)) {
      $this->no_op['base'] = FALSE;
    }
    $this->init_format($this->base);


    if (!empty($this->name_parts)) {
      foreach ($this->name_parts as $name => $formatting) {
        $this->init_format($formatting, $name);
      }
    }
  }

  function init_attrs($mode) {
 //   $and = $this->get_attributes('and');
    if (isset($this->and)) {
      if ($this->and == 'text') {
        $this->and = $this->citeproc->get_locale('term', 'and');
       }
       elseif ($this->and == 'symbol') {
        $this->and = '&';
       }
    }
    if (isset($this->citeproc)) {
      $style_attrs = $this->citeproc->style->get_hier_attributes();
      $mode_attrs = $this->citeproc->{$mode}->get_hier_attributes();
      $this->attributes = array_merge($style_attrs, $mode_attrs, $this->attributes);
    }
    if (!isset($this->delimiter)) {
      $this->delimiter =  $this->{'name-delimiter'} ;
    }
    if (!isset($this->alnum)) {
      list($this->alnum, $this->alpha, $this->cntrl, $this->dash,
      $this->digit, $this->graph, $this->lower, $this->print,
      $this->punct, $this->space, $this->upper, $this->word,
      $this->patternModifiers) = $this->get_regex_patterns();
    }
    $this->dpl = $this->{'delimiter-precedes-last'};
    $this->sort_separator = isset($this->{'sort-separator'}) ? $this->{'sort-separator'} : ', ';
    $this->form = isset($this->form) ? $this->form : 'long';
    $this->attr_init = $mode;
  }

  function init_format($attribs, $part = 'base') {
    if (!isset($this->{$part})) {
      $this->{$part} = array();
    }
    if (isset($attribs['quotes'])) {
      $this->{$part}['open-quote'] = $this->citeproc->get_locale('term', 'open-quote');
      $this->{$part}['close-quote'] = $this->citeproc->get_locale('term', 'close-quote');
      $this->{$part}['open-inner-quote'] = $this->citeproc->get_locale('term', 'open-inner-quote');
      $this->{$part}['close-inner-quote'] = $this->citeproc->get_locale('term', 'close-inner-quote');
      $this->no_op[$part] = FALSE;
    }

    if (isset($attribs['prefix']))  $this->{$part}['prefix'] = $attribs['prefix'];
    if (isset($attribs['suffix']))  $this->{$part}['suffix'] = $attribs['suffix'];

    $this->format[$part] .= (isset($attribs['font-style']))      ? 'font-style: ' . $attribs['font-style'] . ';' : '';
    $this->format[$part] .= (isset($attribs['font-family']))     ? 'font-family: ' . $attribs['font-family'] . ';' : '';
    $this->format[$part] .= (isset($attribs['font-weight']))     ? 'font-weight: ' . $attribs['font-weight'] . ';' : '';
    $this->format[$part] .= (isset($attribs['font-variant']))    ? 'font-variant: ' . $attribs['font-variant'] . ';' : '';
    $this->format[$part] .= (isset($attribs['text-decoration'])) ? 'text-decoration: ' . $attribs['text-decoration'] . ';' : '';
    $this->format[$part] .= (isset($attribs['vertical-align']))  ? 'vertical-align: ' . $attribs['vertical-align'] . ';' : '';

    if (isset($attribs['text-case']))  {
      $this->no_op[$part] = FALSE;
      $this->{$part}['text-case'] = $attribs['text-case'];
    }
    if (!empty($this->format[$part])) $this->no_op[$part] = FALSE;

  }

  function format($text, $part = 'base') {

    if (empty($text) || $this->no_op[$part]) return $text;
    if (isset($this->{$part}['text-case'])) {
      switch ($this->{$part}['text-case']) {
        case 'uppercase':
          $text = mb_strtoupper($text);
          break;
        case 'lowercase':
          $text = mb_strtolower($text);
          break;
        case 'capitalize-all':
          $text = mb_convert_case($text, MB_CASE_TITLE);
          break;
        case 'capitalize-first':
          $chr1 = mb_strtoupper(mb_substr($text, 0, 1));
          $text = $chr1 . mb_substr($text, 1);
          break;
      }
    }
    $open_quote = isset($this->{$part}['open-quote']) ? $this->{$part}['open-quote'] : '';
    $close_quote = isset($this->{$part}['close-quote']) ? $this->{$part}['close-quote'] : '';
    $prefix =  isset($this->{$part}['prefix']) ? $this->{$part}['prefix'] : '';
    $suffix = isset($this->{$part}['suffix']) ? $this->{$part}['suffix'] : '';
    if ($text[(strlen($text) -1)] == $suffix) unset($suffix);
    if (!empty($this->format[$part])) {
      $text = '<span style="' . $this->format[$part] . '">' . $text . '</span>';
    }
    return $prefix . $open_quote . $text . $close_quote . $suffix;
  }

  function render($names, $mode = NULL) {
    $text = '';
    $authors = array();
    $count = 0;
    $auth_count = 0;
    $et_al_triggered = FALSE;
    $initialize_with = $this->{'initialize-with'};

    if (!$this->attr_init || $this->attr_init != $mode) $this->init_attrs($mode);

    foreach ($names as $rank => $name) {
      $count++;
      //$given = (!empty($name->firstname)) ? $name->firstname : '';
      if (!empty($name->given) && isset($initialize_with)) {
          $name->given = preg_replace("/([$this->upper])[$this->lower]+/$this->patternModifiers", '\\1', $name->given);
          $name->given = preg_replace("/(?<=[-$this->upper]) +(?=[-$this->upper])/$this->patternModifiers", "", $name->given);
          if(isset($name->initials)) {
            $name->initials = $name->given .  $name->initials;
          }
          $name->initials = $name->given;
      }
      if (isset($name->initials)) {
        // within initials, remove any dots:
        $name->initials = preg_replace("/([$this->upper])\.+/$this->patternModifiers", "\\1", $name->initials);
        // within initials, remove any spaces *between* initials:
        $name->initials = preg_replace("/(?<=[-$this->upper]) +(?=[-$this->upper])/$this->patternModifiers", "", $name->initials);
        if ($this->citeproc->style->{'initialize-with-hyphen'} == 'false') {
          $name->initials = preg_replace("/-/", '', $name->initials);
        }
        // within initials, add a space after a hyphen, but only if ...
        if (preg_match("/ $/", $initialize_with)) {// ... the delimiter that separates initials ends with a space
          $name->initials = preg_replace("/-(?=[$this->upper])/$this->patternModifiers", "- ", $name->initials);
        }
        // then, separate initials with the specified delimiter:
        $name->initials = preg_replace("/([$this->upper])(?=[^$this->lower]+|$)/$this->patternModifiers", "\\1$initialize_with", $name->initials);

        //      $shortenInitials = (isset($options['numberOfInitialsToKeep'])) ? $options['numberOfInitialsToKeep'] : FALSE;
        //      if ($shortenInitials) $given = drupal_substr($given, 0, $shortenInitials);

        if (isset($initialize_with) ) {
          $name->given = $name->initials;
        }
        elseif(!empty($name->given)) {
          $name->given = $name->given.' '.$name->initials;
        }
        elseif(empty($name->given)) {
          $name->given = $name->initials;
        }
      }

      $ndp = (isset($name->{'non-dropping-particle'})) ? $name->{'non-dropping-particle'} . ' ' : '';
      $suffix = (isset($name->{'suffix'})) ? ' ' . $name->{'suffix'}  : '';

      if (isset($name->given)) {
        $given = $this->format($name->given, 'given');
      }
      if(isset($name->family)) {
        $name->family = $this->format($name->family, 'family');
        if ($this->form == 'short') {
          $text = $ndp . $name->family;
        }
        else {
          switch ($this->{'name-as-sort-order'}) {
            case 'first':
            case 'all':
              $text = $ndp . $name->family . $this->sort_separator . $given;
              break;
            default:
              $text = $given .' '. $ndp . $name->family . $suffix;
          }
        }
        $authors[] = trim($this->format($text));
      }
      
      
      
      // NEW !!!
      if (isset($name->literal)){
      	$text = $name->literal;
      	$authors[] = trim($this->format($text));
      }
      // NEW END!!!
      
      
      
      if (isset($this->{'et-al-min'}) && $count >= $this->{'et-al-min'}) break;
    }
    if (isset($this->{'et-al-min'}) &&
    $count >= $this->{'et-al-min'} &&
    isset($this->{'et-al-use-first'})) {
      if ($this->{'et-al-use-first'} < $this->{'et-al-min'}) {
        for ($i = $this->{'et-al-use-first'}; $i < $count; $i++) {
          unset($authors[$i]);
        }
      }
      if ($this->etal) {
        $authors[] = $this->etal->render();
      }
      else {
        $authors[] = $this->citeproc->get_locale('term', 'et-al');
      }
      $et_al_triggered = TRUE;
    }

    if (!empty($authors) && !$et_al_triggered) {
      $auth_count = count($authors);
      if (isset($this->and) && $auth_count > 1) {
        $authors[$auth_count-1] = $this->and . ' ' . $authors[$auth_count-1]; //stick an "and" in front of the last author if "and" is defined
      }
    }
    $text = implode($this->delimiter, $authors);

   if ($this->form == 'count') {
     if (!$et_al_triggered) {
       return (int)count($authors);
     }
     else {
       return (int)(count($authors) - 1);
     }
   }
    // strip out the last delimiter if not required
    if (isset($this->and) && $auth_count > 1) {
      $last_delim =  strrpos($text, $this->delimiter . $this->and);
      switch ($this->dpl) { //dpl == delimiter proceeds last
        case 'always':
          return $text;
          break;
        case 'never':
          return substr_replace($text, ' ', $last_delim, strlen($this->delimiter));
          break;
        case 'contextual':
        default:
          if ($auth_count < 3) {
            return substr_replace($text, ' ', $last_delim, strlen($this->delimiter));
          }
      }
    }
   return  $text ;
  }

  function get_regex_patterns() {
    // Checks if PCRE is compiled with UTF-8 and Unicode support
    if (!@preg_match('/\pL/u', 'a')) {
      // probably a broken PCRE library
      return $this->get_latin1_regex();
    }
    else {
      // Unicode safe filter for the value
      return $this->get_utf8_regex();
    }
  }

  function get_latin1_regex() {
    $alnum = "[:alnum:]ÄÅÁÀÂÃÇÉÈÊËÑÖØÓÒÔÕÜÚÙÛÍÌÎÏÆäåáàâãçéèêëñöøóòôõüúùûíìîïæÿß";
    // Matches ISO-8859-1 letters:
    $alpha = "[:alpha:]ÄÅÁÀÂÃÇÉÈÊËÑÖØÓÒÔÕÜÚÙÛÍÌÎÏÆäåáàâãçéèêëñöøóòôõüúùûíìîïæÿß";
    // Matches ISO-8859-1 control characters:
    $cntrl = "[:cntrl:]";
    // Matches ISO-8859-1 dashes & hyphens:
    $dash = "-–";
    // Matches ISO-8859-1 digits:
    $digit = "[\d]";
    // Matches ISO-8859-1 printing characters (excluding space):
    $graph = "[:graph:]ÄÅÁÀÂÃÇÉÈÊËÑÖØÓÒÔÕÜÚÙÛÍÌÎÏÆäåáàâãçéèêëñöøóòôõüúùûíìîïæÿß";
    // Matches ISO-8859-1 lower case letters:
    $lower = "[:lower:]äåáàâãçéèêëñöøóòôõüúùûíìîïæÿß";
    // Matches ISO-8859-1 printing characters (including space):
    $print = "[:print:]ÄÅÁÀÂÃÇÉÈÊËÑÖØÓÒÔÕÜÚÙÛÍÌÎÏÆäåáàâãçéèêëñöøóòôõüúùûíìîïæÿß";
    // Matches ISO-8859-1 punctuation:
    $punct = "[:punct:]";
    // Matches ISO-8859-1 whitespace (separating characters with no visual representation):
    $space = "[\s]";
    // Matches ISO-8859-1 upper case letters:
    $upper = "[:upper:]ÄÅÁÀÂÃÇÉÈÊËÑÖØÓÒÔÕÜÚÙÛÍÌÎÏÆ";
    // Matches ISO-8859-1 "word" characters:
    $word = "_[:alnum:]ÄÅÁÀÂÃÇÉÈÊËÑÖØÓÒÔÕÜÚÙÛÍÌÎÏÆäåáàâãçéèêëñöøóòôõüúùûíìîïæÿß";
    // Defines the PCRE pattern modifier(s) to be used in conjunction with the above variables:
    // More info: <http://www.php.net/manual/en/reference.pcre.pattern.modifiers.php>
    $patternModifiers = "";

    return array($alnum, $alpha, $cntrl, $dash, $digit, $graph, $lower,
                 $print, $punct, $space, $upper, $word, $patternModifiers);

  }
  function get_utf8_regex() {
    // Matches Unicode letters & digits:
    $alnum = "\p{Ll}\p{Lu}\p{Lt}\p{Lo}\p{Nd}"; // Unicode-aware equivalent of "[:alnum:]"
    // Matches Unicode letters:
    $alpha = "\p{Ll}\p{Lu}\p{Lt}\p{Lo}"; // Unicode-aware equivalent of "[:alpha:]"
    // Matches Unicode control codes & characters not in other categories:
    $cntrl = "\p{C}"; // Unicode-aware equivalent of "[:cntrl:]"
    // Matches Unicode dashes & hyphens:
    $dash = "\p{Pd}";
    // Matches Unicode digits:
    $digit = "\p{Nd}"; // Unicode-aware equivalent of "[:digit:]"
    // Matches Unicode printing characters (excluding space):
    $graph = "^\p{C}\t\n\f\r\p{Z}"; // Unicode-aware equivalent of "[:graph:]"
    // Matches Unicode lower case letters:
    $lower = "\p{Ll}\p{M}"; // Unicode-aware equivalent of "[:lower:]"
    // Matches Unicode printing characters (including space):
    $print = "\P{C}"; // same as "^\p{C}", Unicode-aware equivalent of "[:print:]"
    // Matches Unicode punctuation (printing characters excluding letters & digits):
    $punct = "\p{P}"; // Unicode-aware equivalent of "[:punct:]"
    // Matches Unicode whitespace (separating characters with no visual representation):
    $space = "\t\n\f\r\p{Z}"; // Unicode-aware equivalent of "[:space:]"
    // Matches Unicode upper case letters:
    $upper = "\p{Lu}\p{Lt}"; // Unicode-aware equivalent of "[:upper:]"
    // Matches Unicode "word" characters:
    $word = "_\p{Ll}\p{Lu}\p{Lt}\p{Lo}\p{Nd}"; // Unicode-aware equivalent of "[:word:]" (or "[:alnum:]" plus "_")
    // Defines the PCRE pattern modifier(s) to be used in conjunction with the above variables:
    // More info: <http://www.php.net/manual/en/reference.pcre.pattern.modifiers.php>
    $patternModifiers = "u"; // the "u" (PCRE_UTF8) pattern modifier causes PHP/PCRE to treat pattern strings as UTF-8
    return array($alnum, $alpha, $cntrl, $dash, $digit, $graph, $lower,
                 $print, $punct, $space, $upper, $word, $patternModifiers);
  }

}

class csl_names extends csl_format {
  private $substitutes;

  function init_formatting() {
 //   $this->span_class = 'authors';
    parent::init_formatting();
  }

  function init($dom_node, $citeproc) {
    $etal = '';
    $tag = $dom_node->getElementsByTagName('substitute')->item(0);
    if ($tag) {
      $this->substitutes = csl_factory::create($tag, $citeproc);
      $dom_node->removeChild($tag);
    }

    $tag = $dom_node->getElementsByTagName('et-al')->item(0);
    if ($tag) {
      $etal = csl_factory::create($tag, $citeproc);
      $dom_node->removeChild($tag);
    }

    $var = $dom_node->getAttribute('variable');
    foreach ($dom_node->childNodes as $node) {
      if ($node->nodeType == 1) {
        $element = csl_factory::create($node, $citeproc);
        if (($element instanceof csl_label)) $element->variable = $var;
        if (($element instanceof csl_name) && $etal) {
          $element->etal = $etal;
        }
        $this->add_element($element);
      }
    }
  }

  function render($data, $mode) {
    $matches = 0;
    $variable_parts = array();
    if (!isset($this->delimiter)) {
      $style_delimiter = $this->citeproc->style->{'names-delimiter'};
      $mode_delimiter = $this->citeproc->{$mode}->{'names-delimiter'};
      $this->delimiter = (isset($mode_delimiter)) ? $mode_delimiter : (isset($style_delimiter) ? $style_delimiter : '');
    }

    $variables  = explode(' ', $this->variable);
    foreach ($variables as $var) {
      if (isset($data->{$var}) && (!empty($data->{$var}))) {
        $matches++;
        break;
      }
    }

    if (!$matches) { // we don't have any primary suspects, so lets check the substitutes...
      if (isset($this->substitutes)) {
        foreach ($this->substitutes->elements as $element) {
          if (($element instanceof csl_names)) { //test to see if any of the other names variables has content
            $variables  = explode(' ', $element->variable);
            foreach ($variables as $var) {
              //list($contributor, $type) = explode(':', $var);
              if (isset($data->{$var}) ) {
                $matches++;
                break;
              }
            }
          }
          else { // if it's not a "names" element, just render it
            return $element->render($data, $mode);
          }
        }
      }
    }

    foreach ($variables as $var) {
      $text = '';
      if (!empty($data->{$var})) {
        foreach ($this->elements as $element) {
            if(is_a($element, 'csl_label')) {
              $data->{$var}['variable'] = $var;
            }
           $text .= $element->render($data->{$var}, $mode);
        }
      }
      if (!empty($text)) $variable_parts[] = $text;
    }

    if (!empty($variable_parts)) {
      $text = implode($this->delimiter, $variable_parts);
      return $this->format($text);
    }

    return ;
  }
}

class csl_date extends csl_format {

  function init($dom_node, $citeproc) {
    $locale_elements = array();

    if ($form = $this->form) {
      $local_date = $this->citeproc->get_locale('date_options', $form);
      $dom_elem = dom_import_simplexml($local_date[0]);
      if ($dom_elem) {
        foreach ($dom_elem->childNodes as $node) {
          if ($node->nodeType == 1) {
            $locale_elements[] = csl_factory::create($node, $citeproc);
          }
        }
      }
      foreach ($dom_node->childNodes as $node) {
        if ($node->nodeType == 1) {
          $element = csl_factory::create($node, $citeproc);

          foreach ($locale_elements as $key => $locale_element) {
            if ($locale_element->name == $element->name) {
              $locale_elements[$key]->attributes = array_merge($locale_element->attributes, $element->attributes);
              $locale_elements[$key]->format =  $element->format;
              break;
            }

            else {
              $locale_elements[] = $element;
            }
          }
        }
      }
      if ($date_parts = $this->{'date-parts'}) {
        $parts = explode('-', $date_parts);
        foreach ($locale_elements as $key => $element) {
          if (array_search($element->name, $parts) === FALSE) {
            unset($locale_elements[$key]);
          }
        }
        if (count($locale_elements) != count($parts)) {
          foreach ($parts as $part) {
            $element = new csl_date_part();
            $element->name = $part;
            $locale_elements[] = $element;
          }
        }
        // now re-order the elements
        foreach ($parts as $part) {
          foreach ($locale_elements as $key => $element)
          if ($element->name == $part) {
            $this->elements[] = $element;
            unset($locale_elements[$key]);
          }
        }

      }
      else {
        $this->elements = $locale_elements;
      }
    }
    else {
      parent::init($dom_node, $citeproc);
    }


  }

  function render($data, $mode) {
    $date_parts = array();
    $date = '';
    $text = '';

    if (($var = $this->variable) && isset($data->{$var})) {
      $date = $data->{$var}->{'date-parts'}[0];
      foreach ($this->elements as $element) {
        $date_parts[] = $element->render($date, $mode);
      }
      $text = implode('', $date_parts);
    }else {
      $text = $this->citeproc->get_locale('term', 'no date');
    }

    return $this->format($text);
  }
}

class csl_date_part extends csl_format {

  function render($date, $mode) {
    $text = '';

    switch ($this->name) {
      case 'year':
        $text = (isset($date[0])) ? $date[0] : '';
        if ($text > 0 && $text < 500) {
          $text = $text . $this->citeproc->get_locale('term', 'ad');
        }
        elseif ($text < 0) {
          $text = $text * -1;
          $text = $text . $this->citeproc->get_locale('term', 'bc');
        }
        //return ((isset($this->prefix))? $this->prefix : '') . $date[0] . ((isset($this->suffix))? $this->suffix : '');
        break;
      case 'month':
        $text = (isset($date[1])) ? $date[1] : '';
        if (empty($text) || $text < 1 || $text > 12) return;
       // $form = $this->form;
        switch ($this->form) {
          case 'numeric': break;
          case 'numeric-leading-zeros':
            if ($text < 10) {
              $text = '0' . $text;
              break;
            }
            break;
          case 'short':
            $month = 'month-' . sprintf('%02d', $text);
            $text = $this->citeproc->get_locale('term', $month, 'short');
            break;
          default:
            $month = 'month-' . sprintf('%02d', $text);
            $text = $this->citeproc->get_locale('term', $month);
            break;
        }
        break;
      case 'day':
        $text = (isset($date[2])) ? $date[2] : '';
        break;
    }

    return $this->format($text);
  }
}

class csl_number extends csl_format {

  function render($data, $mode) {
    $var = $this->variable;

    if (!$var || empty($data->$var)) return;

 //   $form = $this->form;

    switch ($this->form) {
      case 'ordinal':
        $text = $this->ordinal($data->$var);
        break;
      case 'long-ordinal':
        $text = $this->long_ordinal($data->$var);
        break;
      case 'roman':
        $text = $this->roman($data->$var);
        break;
      case 'numeric':
      default:
        $text = $data->$var;
        break;
    }
    return $this->format($text);
  }

  function ordinal($num) {
    if ( ($num/10)%10 == 1) {
      $num .= $this->citeproc->get_locale('term', 'ordinal-04');
    }
    elseif ( $num%10 == 1) {
      $num .= $this->citeproc->get_locale('term', 'ordinal-01');
    }
    elseif ( $num%10 == 2) {
      $num .= $this->citeproc->get_locale('term', 'ordinal-02');
    }
    elseif ( $num%10 == 3) {
      $num .= $this->citeproc->get_locale('term', 'ordinal-03');
    }
    else {
      $num .= $this->citeproc->get_locale('term', 'ordinal-04');
    }
    return $num;

  }

  function long_ordinal($num) {
    $num = sprintf("%02d", $num);
    $ret = $this->citeproc->get_locale('term', 'long-ordinal-' . $num);
    if (!$ret) {
      return $this->ordinal($num);
    }
    return $ret;
  }

  function roman($num) {
    $ret = "";
    if ($num < 6000) {
      $ROMAN_NUMERALS = array(
      array( "", "i", "ii", "iii", "iv", "v", "vi", "vii", "viii", "ix" ),
      array( "", "x", "xx", "xxx", "xl", "l", "lx", "lxx", "lxxx", "xc" ),
      array( "", "c", "cc", "ccc", "cd", "d", "dc", "dcc", "dccc", "cm" ),
      array( "", "m", "mm", "mmm", "mmmm", "mmmmm")
      );
      $numstr = strrev($num);
      $len = strlen($numstr);
      for ($pos = 0; $pos < $len; $pos++) {
        $n = $numstr[$pos];
        $ret = $ROMAN_NUMERALS[$pos][$n] . $ret;
      }
    }

    return $ret;
  }

}

class csl_text extends csl_format {
  public $source;
  protected $var;

  function init($dom_node, $citeproc) {
    foreach (array('variable', 'macro', 'term', 'value') as $attr) {
      if ($dom_node->hasAttribute($attr)) {
        $this->source = $attr;
        if ($this->source == 'macro') {
          $this->var =  str_replace(' ', '_', $dom_node->getAttribute($attr));
        }
        else {
          $this->var =  $dom_node->getAttribute($attr);
        }
      }
    }
  }
  function init_formatting() {
//    if ($this->variable == 'title') {
//      $this->span_class = 'title';
//    }
    parent::init_formatting();

  }

  function render($data = NULL, $mode = NULL) {
    $text = '';

    switch ($this->source) {
      case 'variable':
        if(!isset($data->{$this->variable}) || empty($data->{$this->variable})) return;
        $text = $data->{$this->variable}; //$this->data[$this->var];  // include the contents of a variable
        break;
      case 'macro':
        $macro = $this->var;
        $text = $this->citeproc->render_macro($macro, $data, $mode); //trigger the macro process
        break;
      case 'term':
        $form = (($form = $this->form)) ? $form : '';
        $text = $this->citeproc->get_locale('term', $this->var, $form);
        break;
      case 'value':
        $text = $this->var; //$this->var;  // dump the text verbatim
        break;
    }

    if (empty($text)) return;
    return $this->format($text);
  }
}

class csl_et_al extends csl_text {

  function __construct($dom_node = NULL, $citeproc = NULL) {
    $this->var = 'et-al';
    $this->source = 'term';
    parent::__construct($dom_node, $citeproc);

    }
}
class csl_label extends csl_format {
  private $plural;

  function render($data, $mode = NULL) {
    $text = '';

    $variables = explode(' ', $this->variable);
    $form = (($form = $this->form)) ? $form : 'long';
    switch ($this->plural) {
      case 'never':
        $plural = 'single';
        break;
      case 'always':
        $plural = 'multiple';
        break;
      case 'contextual':
      default:
        if (count($data) == 1) {
          $plural = 'single';
        }
        elseif (count($data) > 1) {
          $plural = 'multiple';
        }
    }
    if (is_array($data) && isset($data['variable'])) {
      $text = $this->citeproc->get_locale('term', $data['variable'], $form, $plural);
    }
    if (empty($text)) {
    foreach ($variables as $variable) {
      if (($term = $this->citeproc->get_locale('term', $variable, $form, $plural))) {
        $text = $term;
        break;
      }
    }
    }
    if (empty($text)) return;
    if ($this->{'strip-periods'}) $text = str_replace('.', '', $text);
    return $this->format($text);
  }
}

class csl_macro extends csl_format{

}

class csl_macros extends csl_collection{

  function __construct($macro_nodes, $citeproc) {
    foreach ($macro_nodes as $macro) {
      $macro = csl_factory::create($macro, $citeproc);
      $this->elements[$macro->name()] = $macro;
    }
  }

  function render_macro($name, $data, $mode) {
    return $this->elements[$name]->render($data, $mode);
  }
}

class csl_group extends csl_format{

  function render($data, $mode) {
    $text = '';
    $text_parts = array();

    $terms = $variables = $have_variables = 0;
    foreach ($this->elements as $element) {
      if (($element instanceof csl_text) &&
          ($element->source == 'term' ||
           $element->source == 'value' )) {
        $terms++;
      }
      if (($element instanceof csl_label)) $terms++;
      if ($element->source == 'variable' &&
          isset($element->variable) &&
          !empty($data->{$element->variable})
         ) {
        $variables++;
      }

      $text = $element->render($data, $mode);

      if (!empty($text)) {
        $text_parts[] = $text;
        if ($element->source == 'variable' || isset($element->variable)) $have_variables++;
        if ($element->source == 'macro') $have_variables++;
      }
    }

    if (empty($text_parts)) return;
    if ($variables  && !$have_variables ) return; // there has to be at least one other none empty value before the term is output
    if (count($text_parts) == $terms) return; // there has to be at least one other none empty value before the term is output

    $delimiter = $this->delimiter;
    $text = implode($delimiter, $text_parts); // insert the delimiter if supplied.


    return $this->format($text);
  }
}

class csl_layout extends csl_format {

  function init_formatting() {
    //$this->div_class = 'csl-entry';
    parent::init_formatting();
  }

  function render($data, $mode) {
    $text = '';
    $parts = array();
   // $delimiter = $this->delimiter;

    foreach ($this->elements as $element) {
      $parts[] = $element->render($data, $mode);
    }

    $text = implode($this->delimiter, $parts);

    if ($mode == 'bibliography') {
      return $this->format($text);
    }
    else {
      return $text;
    }

  }

}

class csl_citation extends csl_format{
  private $layout = NULL;

  function init($dom_node, $citeproc) {
    $options = $dom_node->getElementsByTagName('option');
    foreach ($options as $option) {
      $value = $option->getAttribute('value');
      $name  = $option->getAttribute('name');
      $this->attributes[$name]  = $value;
    }

    $layouts = $dom_node->getElementsByTagName('layout');
    foreach ($layouts as $layout) {
      $this->layout = new csl_layout($layout, $citeproc);
    }
  }

  function render($data, $mode = NULL) {
    $text = $this->layout->render($data, 'citation');

    return $this->format($text);
  }

}
class csl_bibliography  extends csl_format {
  private $layout = NULL;

  function init($dom_node, $citeproc) {
    $hier_name_attr = $this->get_hier_attributes();
    $options = $dom_node->getElementsByTagName('option');
    foreach ($options as $option) {
      $value = $option->getAttribute('value');
      $name  = $option->getAttribute('name');
      $this->attributes[$name]  = $value;
    }

    $layouts = $dom_node->getElementsByTagName('layout');
    foreach ($layouts as $layout) {
      $this->layout = new csl_layout($layout, $citeproc);
    }

  }

  function init_formatting() {
    $this->div_class = 'csl-bib-body';
    parent::init_formatting();
  }

  function render($data, $mode = NULL) {
    $text = $this->layout->render($data, 'bibliography');
    if ($this->{'hanging-indent'} == 'true') {
      // apa Style uses this... reactivate if necessary
      //$text = '<div style="  text-indent: -25px; padding-left: 25px;">' . $text . '</div>';
    }
    $text = str_replace('?.', '?', str_replace('..', '.', $text));
    return $this->format($text);
  }
}

class csl_option  {
  private $name;
  private $value;

  function get() {
    return array($this->name => $this->value);
  }
}

class csl_options extends csl_element{

}

class csl_sort extends csl_element{

}
class csl_style extends csl_element{

  function __construct($dom_node = NULL, $citeproc = NULL) {
    if ($dom_node) {
      $this->set_attributes($dom_node);
    }
  }
}

class csl_choose extends csl_element{

  function render($data, $mode = NULL) {
    foreach ($this->elements as $choice) {
      if ($choice->evaluate($data)) {
        return $choice->render($data, $mode);
      }
    }
  }
}

class csl_if extends csl_rendering_element {

  function evaluate($data) {
    $match = (($match = $this->match)) ? $match : 'all';
    if (($types = $this->type)) {
      $types  = explode(' ', $types);
      $matches = 0;
      foreach ($types as $type) {
        if (isset($data->type)) {
          if ($data->type == $type && $match == 'any') return TRUE;
          if ($data->type != $type && $match == 'all') return FALSE;
          if ($data->type == $type) $matches++;
        }
      }
      if ($match == 'all' && $matches == count($types)) return TRUE;
      if ($match == 'none' && $matches == 0) return TRUE;
      return FALSE;
    }
    if (($variables = $this->variable)) {
      $variables  = explode(' ', $variables);
      $matches = 0;
      foreach ($variables as $var) {
        if (isset($data->$var) && !empty($data->$var) && $match == 'any') return TRUE;
        if ((!isset($data->$var) || empty($data->$var)) && $match == 'all') return FALSE;
        if (isset($data->$var) && !empty($data->$var)) $matches++;
      }
      if ($match == 'all' && $matches == count($variables)) return TRUE;
      if ($match == 'none' && $matches == 0) return TRUE;
      return FALSE;
    }
    if (($is_numeric = $this->{'is-numeric'})) {
      $variables  = explode(' ', $is_numeric);
      $matches = 0;
      foreach ($variables as $var) {
        if (isset($data->$var)) {
          if (is_numeric($data->$var) && $match == 'any') return TRUE;
          if (!is_numeric($data->$var)) {
            if (preg_match('/(?:^\d+|\d+$)/', $data->$var)) {
              $matches++;
            }
            elseif ($match == 'all') {
              return FALSE;
            }
          }
          if (is_numeric($data->$var)) $matches++;
        }
      }
      if ($match == 'all' && $matches == count($variables)) return TRUE;
      if ($match == 'none' && $matches == 0) return TRUE;
      return FALSE;
    }
    if (isset($this->locator))  $test  = explode(' ', $this->type);

    return FALSE;
  }
}

class csl_else_if extends csl_if {

}

class csl_else extends csl_if {

  function evaluate($data = NULL) {
    return TRUE; // the last else always returns TRUE
  }
}

class csl_substitute extends csl_element{

}

class csl_locale  {
  protected $locale_xmlstring = NULL;
  protected $style_locale_xmlstring = NULL;
  protected $locale = NULL;
  protected $style_locale = NULL;
  private   $module_path;

  function __construct($lang = 'en') {
    //$this->module_path = '.';
    
  	
  	// NEW! Get server name and add folder name of Citeproc folder (must be in htdocs)
    $this->module_path = 'http://' . preg_replace('/:5000/', '', $_SERVER['SERVER_NAME']) . '/CiteprocPhp';
    // NEW
    
    
    $this->locale = new SimpleXMLElement($this->get_locales_file_name($lang));
    if ($this->locale) {
      $this->locale->registerXPathNamespace('cs', 'http://purl.org/net/xbiblio/csl');
    }
  }

  // SimpleXML objects cannot be serialized, so we must convert to an XML string prior to serialization
  function __sleep() {
    $this->locale_xmlstring       = ($this->locale)       ? $this->locale->asXML()       : '';
    $this->style_locale_xmlstring = ($this->style_locale) ? $this->style_locale->asXML() : '';
    return array('locale_xmlstring', 'style_locale_xmlstring');
  }

  // SimpleXML objects cannot be serialized, so when un-serializing them, they must rebuild from the serialized XML string.
  function __wakeup() {
    $this->style_locale = (!empty($this->style_locale_xmlstring)) ? new SimpleXMLElement($this->style_locale_xmlstring) : NULL;
    $this->locale       = (!empty($this->locale_xmlstring))       ? new SimpleXMLElement($this->locale_xmlstring)       : NULL;
    if ($this->locale) {
      $this->locale->registerXPathNamespace('cs', 'http://purl.org/net/xbiblio/csl');
    }
  }

  function get_locales_file_name($lang) {
    $lang_bases = array(
        "af" => "af-ZA",
        "ar" => "ar-AR",
        "bg" => "bg-BG",
        "ca" => "ca-AD",
        "cs" => "cs-CZ",
        "da" => "da-DK",
        "de" => "de-DE",
        "el" => "el-GR",
        "en" => "en-US",
        "es" => "es-ES",
        "et" => "et-EE",
        "fr" => "fr-FR",
        "he" => "he-IL",
        "hu" => "hu-HU",
        "is" => "is-IS",
        "it" => "it-IT",
        "ja" => "ja-JP",
        "ko" => "ko-KR",
        "mn" => "mn-MN",
        "nb" => "nb-NO",
        "nl" => "nl-NL",
        "pl" => "pl-PL",
        "pt" => "pt-PT",
        "ro" => "ro-RO",
        "ru" => "ru-RU",
        "sk" => "sk-SK",
        "sl" => "sl-SI",
        "sr" => "sr-RS",
        "sv" => "sv-SE",
        "th" => "th-TH",
        "tr" => "tr-TR",
        "uk" => "uk-UA",
        "vi" => "vi-VN",
        "zh" => "zh-CN",
    );
    
    return (isset($lang_bases[$lang])) ? file_get_contents($this->module_path . '/locale/locales-' . $lang_bases[$lang] . '.xml') : file_get_contents($this->module_path . '/locale/locales-en-US.xml');
    //return (isset($lang_bases[$lang])) ? file_get_contents('http://ubcsl.ub.uni-bielefeld.de/newCiteprocTest/locale/locales-' . $lang_bases[$lang] . '.xml') : file_get_contents('http://ubcsl.ub.uni-bielefeld.de/newCiteprocTest/locale/locales-en-US.xml');
    //return (isset($lang_bases[$lang])) ? file_get_contents('http://pub.uni-bielefeld.de/CiteprocPhp/locale/locales-' . $lang_bases[$lang] . '.xml') : file_get_contents('http://pub.uni-bielefeld.de/CiteprocPhp/locale/locales-en-US.xml');
  }

  function get_locale($type, $arg1, $arg2 = NULL, $arg3 = NULL) {
    switch ($type) {
      case 'term':
        $term = '';
        $form = $arg2 ? " and @form='$arg2'" : '';
        $plural = $arg3 ? "/cs:$arg3" : '';
        if ($this->style_locale) {
          $term = @$this->style_locale->xpath("//locale[@xml:lang='en']/terms/term[@name='$arg1'$form]$plural");
          if (!$term) {
            $term = @$this->style_locale->xpath("//locale/terms/term[@name='$arg1'$form]$plural");
          }
        }
        if (!$term) {
          $term = $this->locale->xpath("//cs:term[@name='$arg1'$form]$plural");
        }
        if (isset($term[0])){
          if (isset($arg3) && isset($term[0]->{$arg3})) return (string)$term[0]->{$arg3};
          if (!isset($arg3) && isset($term[0]->single)) return (string)$term[0]->single;
          return (string)$term[0];
        }
        break;
      case 'date_option':
        $attribs = array();
        if ($this->style_locale) {
          $date_part = $this->style_locale->xpath("//date[@form='$arg1']/date-part[@name='$arg2']");
        }
        if (!isset($date_part)) {
          $date_part = $this->locale->xpath("//cs:date[@form='$arg1']/cs:date-part[@name='$arg2']");
        }
        if (isset($date_part)) {
          foreach ($$date_part->attributes()  as $name => $value) {
            $attribs[$name] = (string)$value;
          }
        }
        return $attribs;
        break;
      case 'date_options':
        $options = array();
        if ($this->style_locale) {
          $options = $this->style_locale->xpath("//locale[@xml:lang='en']/date[@form='$arg1']");
          if (!$options) {
            $options = $this->style_locale->xpath("//locale/date[@form='$arg1']");
          }
        }
        if (!$options) {
          $options = $this->locale->xpath("//cs:date[@form='$arg1']");
        }
        if (isset($options[0]))return $options[0];
        break;
      case 'style_option':
        $attribs = array();
        if ($this->style_locale) {
          $option = $this->style_locale->xpath("//locale[@xml:lang='en']/style-options[@$arg1]");
          if (!$option) {
            $option = $this->style_locale->xpath("//locale/style-options[@$arg1]");
          }
        }
        if (isset($option) && !empty($option)) {
          $attribs = $option[0]->attributes();
        }
        if (empty($attribs)) {
          $option = $this->locale->xpath("//cs:style-options[@$arg1]");
        }
        foreach ($option[0]->attributes()  as $name => $value) {
          if ($name == $arg1) return (string)$value;
        }
        break;
    }
  }

  public function set_style_locale($csl_doc) {
    $xml = '';
    $locale_nodes = $csl_doc->getElementsByTagName('locale');
    if ($locale_nodes) {
      $xml_open = '<style-locale>';
      $xml_close = '</style-locale>';
      foreach ($locale_nodes as $key => $locale_node) {
        $xml .= $csl_doc->saveXML($locale_node);
      }
      if (!empty($xml)) {
        $this->style_locale = new SimpleXMLElement($xml_open . $xml . $xml_close);
      }
    }
  }

}

class csl_mapper {
  // In the map_field and map_type function below, the array keys hold the "CSL" variable and type names
  // and the array values contain the variable and type names of the incomming data object.  If the naming
  // convention of your incomming data object differs from the CSL standard (http://citationstyles.org/downloads/specification.html#id78)
  // you should adjust the array values accordingly.

  function map_field($field) {
    if (!isset($this->field_map)) {
      $this->field_map = array('title'                       => 'title',
                               'container-title'             => 'container-title',
                               'collection-title'            => 'collection-title',
                               'original-title'              => 'original-title',
                               'publisher'                   => 'publisher',
                               'publisher-place'             => 'publisher-place',
                               'original-publisher'          => 'original-publisher',
                               'original-publisher-place'    => 'original-publisher-place',
                               'archive'                     => 'archive',
                               'archive-place'               => 'archive-place',
                               'authority'                   => 'authority',
                               'archive_location'            => 'authority',
                               'event'                       => 'event',
                               'event-place'                 => 'event-place',
                               'page'                        => 'page',
                               'page-first'                  => 'page',
                               'locator'                     => 'locator',
                               'version'                     => 'version',
                               'volume'                      => 'volume',
                               'number-of-volumes'           => 'number-of-volumes',
                               'number-of-pages'             => 'number-of-pages',
                               'issue'                       => 'issue',
                               'chapter-number'              => 'chapter-number',
                               'medium'                      => 'medium',
                               'status'                      => 'status',
                               'edition'                     => 'edition',
                               'section'                     => 'section',
                               'genre'                       => 'genre',
                               'note'                        => 'note',
                               'annote'                      => 'annote',
                               'abstract'                    => 'abstract',
                               'keyword'                     => 'keyword',
                               'number'                      => 'number',
                               'references'                  => 'references',
                               'URL'                         => 'URL',
                               'DOI'                         => 'DOI',
                               'ISBN'                        => 'ISBN',
                               'call-number'                 => 'call-number',
                               'citation-number'             => 'citation-number',
                               'citation-label'              => 'citation-label',
                               'first-reference-note-number' => 'first-reference-note-number',
                               'year-suffix'                 => 'year-suffix',
                               'jurisdiction'                => 'jurisdiction',

                               //Date Variables'
                               'issued'                      => 'issued',
                               'event'                       => 'event',
                               'accessed'                    => 'accessed',
                               'container'                   => 'container',
                               'original-date'               => 'original-date',

                               //Name Variables'
                               'author'                      => 'author',
                               'editor'                      => 'editor',
                               'translator'                  => 'translator',
                               'recipient'                   => 'recipient',
                               'interviewer'                 => 'interviewer',
                               'publisher'                   => 'publisher',
                               'composer'                    => 'composer',
                               'original-publisher'          => 'original-publisher',
                               'original-author'             => 'original-author',
                               'container-author'            => 'container-author',
                               'collection-editor'           => 'collection-editor',
                              );
    }
    $vars = explode(' ', $field);
    foreach ($vars as $key => $value) {
      $vars[$key] = (!empty($this->field_map[$value])) ? $this->field_map[$value] : '';
    }

    return implode(' ', $vars);
  }

  function map_type($types) {
    if (!isset($this->type_map)) {
      $this->type_map = array(
                        'article' => 'article',
                        'article-magazine'  => 'article-magazine',
                        'article-newspaper' => 'article-newspaper',
                        'article-journal' => 'article-journal',
                        'bill' => 'bill',
                        'book'  => 'book',
                        'broadcast' => 'broadcast',
                        'chapter' => 'chapter',
                        'entry' => 'entry',
                        'entry-dictionary'  => 'entry-dictionary',
                        'entry-encyclopedia'  => 'entry-encyclopedia',
                        'figure'  => 'figure',
                        'graphic'  => 'graphic',
                        'interview'  => 'interview',
                        'legislation' => 'legislation',
                        'legal_case' => 'legal_case',
                        'manuscript' => 'manuscript',
                        'map' => 'map',
                        'motion_picture' => 'motion_picture',
                        'musical_score'  => 'musical_score',
                        'pamphlet'  => 'pamphlet',
                        'paper-conference' => 'paper-conference',
                        'patent' => 'patent',
                        'post'  => 'post',
                        'post-weblog'  => 'post-weblog',
                        'personal_communication' => 'personal_communication',
                        'report' => 'report',
                        'review'  => 'review',
                        'review-book'  => 'review-book',
                        'song'  => 'song',
                        'speech'  => 'speech',
                        'thesis' => 'thesis',
                        'treaty'  => 'treaty',
                        'webpage' => 'webpage',
      );
    }
    $vars = explode(' ', $types);
    foreach ($vars as $key => $value) {
      $vars[$key] = (!empty($this->type_map[$value])) ? $this->type_map[$value] : '';
    }

    return implode(' ', $vars);

  }
}


header('Content-type: text/plain');

// Eingabeverarbeitung, Defaults
$input = $_REQUEST;

//foreach ($_REQUEST['_content'] as $value){//$argv as $value){
	//$val_array = split("=",$value);
	//$val_array = split("=",$_REQUEST['_content']);
	//if($val_array[0] == 'input'){
	//	$input['input'] = $val_array[1];
	//} elseif ($val_array[0] == 'style'){
	//	$input['style'] = $val_array[1];
	//} elseif ($val_array[0] == 'format'){
	//	$input['format'] = $val_array[1];
	//}
//}
/* Debug auskommentiert 14.09.2011 Artur Nold
 $filename = '/var/log/citeproc-php/debug.log';
//                 "\n" . date(DATE_ATOM) .
//                 "\n" . $_SERVER['REMOTE_ADDR'] .

$somecontent =  "\n\n" . date(DATE_ATOM) . "\n" . $_SERVER['REMOTE_ADDR'] ."\nInput:\n" . implode("\n", $input);
$handle = fopen($filename, "a");
fwrite($handle, $somecontent);
fclose($handle);
Debug ENDE Artur Nold */

// if (is_writable($filename)) {
//     if (!$handle = fopen($filename, "a")) {
//          exit;
//     }
//     if (!fwrite($handle, $somecontent)) {
//         exit;
//     }
//     fclose($handle);
// }

if (false === isset($input['input']) OR true === empty($input['input'])) {
	die("Keine Metadaten erhalten");
}

if (false === isset($input['style']) OR true === empty($input['style'])) {
	$input['style'] = 'plos';
}

if (false === isset($input['format']) OR true === empty($input['format'])) {
	$input['format'] = 'html';
}

// Style-Dateinamen bauen
//$csl_style = 'http://ubcsl.ub.uni-bielefeld.de/newCiteprocTest/style/'. $input['style'].'.csl';
//$csl_style = 'http://pub.uni-bielefeld.de/CiteprocPhp/style/' . $input['style'] . '.csl';
$csl_style = 'http://'. preg_replace('/:5000/', '', $_SERVER['SERVER_NAME']) . '/CiteprocPhp/style/'. $input['style'] . '.csl';
//$csl_style = 'http://'.$_SERVER['SERVER_NAME'].'/CiteprocPhp/style/' . $input['style'] . '.csl';
// Style-Datei laden ( TODO: Try-Catch)
//$ch = curl_init();
//curl_setopt($ch, CURLOPT_HEADER, 0);
//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
//curl_setopt($ch, CURLOPT_URL, $csl_style);
//$csl_data = curl_exec($ch) or die ('did not get csl_data');
//curl_close($ch);
//print_r ($csl_data);

$csl_data = file_get_contents($csl_style);

//preg_match_all('/<sort>(.*?)<\/sort>/',$csl_data, $matches);

//$sort_me = substr($csl_data, strpos($csl_data, "<bibliography "));
//$sort_me = substr($sort_me, 0, strpos($sort_me, "</sort>"));
//print_r($matches[0]); exit();
// print_r($input);exit();
// Aus uebergebenem JSON-Array ein PHP-Array bauen
// $input_data = (array) json_decode(utf8_encode($input['input']));
/*$input['input'] = str_replace($input['input'], '<', '&lt;');
 $input['input'] = str_replace($input['input'], '>', '&lt;');
$input['input'] = str_replace($input['input'], "'", '`');*/
//$input_data = (array) json_decode($input['input']);
//print_r ($input['input']); exit;
//$input_data = json_decode(utf8_decode($input['input']));
$input_data = json_decode($input['input']);
#print_r (urldecode($input['input']));
//$input_dec = urldecode($input['input']);

//echo ($input_dec)."\n";
//$input_data = json_decode($input_dec);

//foreach ($input_data as $sortdata){
	// first author
//	if($sortdata->{author}[0]->{family}){
//		$temparray['author_full'] = $sortdata->{author}[0]->{family};
//	}
//	elseif($sortdata->{author}[0]->{literal}){
//		$temparray['author_full'] = $sortdata->{author}[0]->{literal};
//	}
//	$temparray['id'] = $sortdata->{id};
//	$sortarray[] = $temparray;
//	$temparray = array();
//}

//foreach ($sortarray as $key => $row) {
//	$volume[$key]  = $row['author-full'];
//	$edition[$key] = $row['id'];
//}

//array_multisort($volume, SORT_DESC, $sortarray);
//foreach ($sortarray as $id){
//	$sort[] = $id['id'];
//}

//print_r($sort); exit();

//array_multisort($input_data, SORT_DESC, $sort);

//function sortArrayByArray($array,$orderArray) {
//	$ordered = array();
//	foreach($orderArray as $value => $key) {
//		if(array_key_exists($key,$array)) {
//			$ordered[$key] = $array[$key];
//			unset($array[$key]);
//		}
//	}
//	return $ordered + $array;
//}

//$new_array = sortArrayByArray($input_data, $sort);

//print_r($sort); exit();

//print_r($new_array); exit();

//array_multisort($sortarray, SORT_ASC, SORT_STRING);

//print_r($sortarray); exit();

//$data = $input_data;
#print_r($input_data); exit();

//print_r(json_decode(utf8_encode($input['input'])));exit();

// CSL-Prozessor instanziieren
$citeproc = new citeproc($csl_data);

// Fuer jeden Metadatensatz ein gerenderter Zitationsstring
foreach($input_data as $data) {
	//echo $citeproc->render($data).'<br>';
	$data->csl_citations[$input['style']] = $citeproc->render($data);
	// print_r($data->csl_citations[$input['style']]);

	// if ('html' !== $input['format']) {
	if ('html' === $input['format']) {
		$ersetzung['bold']      = '__HTML_STRONG_START__$1__HTML_STRONG_END__';
		$ersetzung['italic']    = '__HTML_EM_START__$1__HTML_EM_END__';
		$ersetzung['normal']    = '$1';
		$ersetzung['div']       = '__HTML_DIV_START__$1__HTML_DIV_MID__$2__HTML_DIV_END__';
		$ersetzung['span']      = '__HTML_SPAN_START__$1__HTML_SPAN_MID__$2__HTML_SPAN_END__';
	} elseif ('plain' === $input['format']) {
		$ersetzung['italic'] = '/$1/';
		$ersetzung['bold'] = '*$1*';
	} elseif('rtf' === $input['format']) {
		$ersetzung['italic'] = '{\i $1}';
		$ersetzung['bold'] = '{\b $1}';
	} elseif('tex' === $input['format']) {
		$ersetzung['italic'] = '{\it $1}';
		$ersetzung['bold'] = '{\bf $1}';
	}
	foreach (array_keys($ersetzung) as $emph) {
		//             $suchmuster[$emph] = '#<span [^>]*' . $emph . '[^>]*>([^<]*)</span>#i';//(\d+)\. (\w+) (\d+)  (?!\x20+m�ller|m�ller)
		$suchmuster[$emph] = '#<span [^>]*' . $emph . '[^>]*>(.*?)</span>#i';//(\d+)\. (\w+) (\d+)  (?!\x20+m�ller|m�ller)
	}
	// Alle anderen Tags werden gestrippt. Ist natuerlich bloed wenn vorformatiert wurde (Biokybernetiker)
	if ('html' === $input['format']) {
		$suchmuster['div'] = '#<div ([^>]*)>(.*)</div>#i';
		$suchmuster['span'] = '#<span ([^>]*)>(.*)</span>#i';
		$data->csl_citations[$input['style']] = preg_replace($suchmuster, $ersetzung, $data->csl_citations[$input['style']]);
		//print_r($data->csl_citations[$input['style']]);

		$data->csl_citations[$input['style']] = htmlspecialchars($data->csl_citations[$input['style']], ENT_NOQUOTES);
		$data->csl_citations[$input['style']] = str_replace(
				array(  '__HTML_EM_START__',
						'__HTML_EM_END__',
						'__HTML_STRONG_START__',
						'__HTML_STRONG_END__',
						'__LINE_BREAK__',
						'__HTML_DIV_START__',
						'__HTML_DIV_MID__',
						'__HTML_DIV_END__',
						'__HTML_SPAN_START__',
						'__HTML_SPAN_MID__',
						'__HTML_SPAN_END__'),
				array('<em>',
						'</em>',
						'<strong>',
						'</strong>',
						'<br />',
						'<div ',
						'>',
						'</div>',
						'<span ',
						'>',
						'</span>'),
				$data->csl_citations[$input['style']]
		);
	}

	//if(!isset($data->{'URL'})){ $data->{'URL'} = '';}
	//if(!isset($data->{'DOI'})){ $data->{'DOI'} = '';}
	//if(!isset($data->{'constituent'})){ $data->{'constituent'} = '';}
	
	$return_array['citation'] = $data->csl_citations[$input['style']];
	$return_array['recordid'] = $data->{'recordid'};
	#$return_array['id'] = $data->{'id'};
	if(isset($data->{'URL'})) $return_array['URL'] = $data->{'URL'};
	if(isset($data->{'DOI'})) $return_array['DOI'] = $data->{'DOI'};
	if(isset($data->{'constituent'})) $return_array['constituent'] = $data->{'constituent'};
	
	$output[] = $return_array;
	$return_array = array();
	//$output[] = array('citation'    => $data->csl_citations[$input['style']],
	//		          'recordid'    => $data->{'id'},
	//		          'URL'         => $data->{'URL'},
	//		          'DOI'         => $data->{'DOI'},
	//		          'constituent' => $data->{'constituent'}
	//); 
}

echo json_encode($output);

exit();
