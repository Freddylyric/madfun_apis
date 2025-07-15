<?php

/**
 * Description of XMLToArrayUtils
 */
class XMLToArrayUtils
{
    private array $_data = [];
    private array $_name = [];
    private array $_rep = [];
    private $_parser = null;
    private array $_ignore = [], $_replace = [];
    private bool $_showAttribs;
    private int $_level = 0;

    public function __construct($data, array $ignore = [], array $replace = [], bool $showattribs = false, bool $toupper = false)
    {
        if (preg_match('@^(https?|ftp)://@', $data)) {
            if ($stream = fopen($data, 'r')) {
                $data = stream_get_contents($stream);
                fclose($stream);
            } else {
                throw new \RuntimeException("Unable to read URL: $data");
            }
        } elseif (file_exists($data)) {
            $data = file_get_contents($data);
        }

        $this->_showAttribs = $showattribs;
        $this->_parser = xml_parser_create();

        xml_set_object($this->_parser, $this);

        if ($toupper) {
            foreach ((array)$ignore as $key => $value) {
                $this->_ignore[strtoupper($key)] = strtoupper($value);
            }
            foreach ((array)$replace as $key => $value) {
                $this->_replace[strtoupper($key)] = strtoupper($value);
            }
            xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, true);
        } else {
            $this->_ignore = $ignore;
            $this->_replace = $replace;
            xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, false);
        }

        xml_set_element_handler($this->_parser, '_startElement', '_endElement');
        xml_set_character_data_handler($this->_parser, '_cdata');

        $this->_data = [];
        $this->_level = 0;

        if (!xml_parse($this->_parser, $data, true)) {
            $error = sprintf(
                "XML Parse Error: %s on line %d",
                xml_error_string(xml_get_error_code($this->_parser)),
                xml_get_current_line_number($this->_parser)
            );
            xml_parser_free($this->_parser);
            throw new \RuntimeException($error);
        }

        xml_parser_free($this->_parser);
    }

    public function &getArray()
    {
        return $this->_data[0];
    }

    public function &getReplaced()
    {
        return $this->_data['_Replaced_'];
    }

    public function &getAttributes()
    {
        return $this->_data['_Attributes_'];
    }

    private function _startElement($parser, $name, $attrs)
    {
        if (!isset($this->_rep[$name])) {
            $this->_rep[$name] = 0;
        }

        if (!in_array($name, $this->_ignore)) {
            $this->_addElement($name, $this->_data[$this->_level], $attrs, true);
            $this->_name[$this->_level] = $name;
            $this->_level++;
        }
    }

    private function _endElement($parser, $name)
    {
        if (!in_array($name, $this->_ignore) && isset($this->_name[$this->_level - 1])) {
            if (isset($this->_data[$this->_level])) {
                $this->_addElement(
                    $this->_name[$this->_level - 1],
                    $this->_data[$this->_level - 1],
                    $this->_data[$this->_level],
                    false
                );
            }

            unset($this->_data[$this->_level]);
            $this->_level--;
            $this->_rep[$name]++;
        }
    }

    private function _cdata($parser, $data)
    {
        if (!empty($this->_name[$this->_level - 1])) {
            $this->_addElement(
                $this->_name[$this->_level - 1],
                $this->_data[$this->_level - 1],
                str_replace(['&gt;', '&lt;', '&quot;', '&amp;'], ['>', '<', '"', '&'], $data),
                false
            );
        }
    }

    private function _addElement(&$name, &$start, $add = [], $isattribs = false)
    {
        if (((is_array($add) && count($add) === 0) || !$add)) {
            if (!isset($start[$name])) {
                $start[$name] = '';
            }
            $add = '';
        }

        if (!empty($this->_replace[$name]) && strtoupper($this->_replace[$name]) === '_ARRAY_') {
            if (!isset($start[$name])) {
                $this->_rep[$name] = 0;
            }
            $update = &$start[$name][$this->_rep[$name]];
        } elseif (!empty($this->_replace[$name])) {
            if (!empty($add[$this->_replace[$name]])) {
                $this->_data['_Replaced_'][$add[$this->_replace[$name]]] = $name;
                $name = $add[$this->_replace[$name]];
            }
            $update = &$start[$name];
        } else {
            $update = &$start[$name];
        }

        if ($isattribs && !$this->_showAttribs) {
            return;
        } elseif ($isattribs) {
            $this->_data['_Attributes_'][$this->_level][$name][] = $add;
        } elseif (is_array($add) && is_array($update)) {
            $update += $add;
        } elseif (is_array($update)) {
            return;
        } elseif (is_array($add)) {
            $update = $add;
        } elseif ($add) {
            $update .= $add;
        }
    }
}
