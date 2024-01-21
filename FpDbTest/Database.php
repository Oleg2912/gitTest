<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery($template, $params = []): string
    {
        $query = $template;
        $paramIndex = 0;

        if (!empty($params)) {
            if ($params[array_key_last($params)] === false) {
                $query = preg_replace('/{(.*?)}/', '', $query);
            }
            if ($params[array_key_last($params)] === true) {
                $query = preg_replace('/[{}]/', '', $query);
            }
        }

        while (strpos($query, '?') !== false) {
            if ($paramIndex >= count($params)) {
                throw new Exception('Not enough parameters for the template');
            }

            $param = $params[$paramIndex];
            $paramIndex++;

            if (preg_match('/\?(d|f|a|#)/', $query, $matches, PREG_OFFSET_CAPTURE)) {
                $specifier = $matches[1][0];
                $pos = $matches[0][1];

                if ($specifier === 'd') {
                    if (!is_int((int)$param)) {
                        throw new Exception('Expected an integer parameter');
                    }
                    $replacement = (string)$param;
                } elseif ($specifier === 'f') {
                    if (!is_float($param)) {
                        throw new Exception('Expected a float parameter');
                    }
                    $replacement = (string)$param;
                } elseif ($specifier === 'a') {
                    if (!is_array($param)) {
                        throw new Exception('Expected an array parameter');
                    }
                    if (array_is_list($param)) {
                        $replacement = implode(', ', $param);
                    } else {
                        $set = '';
                        foreach ($param as $key => $value) {
                            if (isset($value)) {
                                $value = addslashes($value);
                                $set .= "`$key` = '$value', ";
                            } else {
                                $set .= "`$key` = NULL, ";
                            }
                        }
                        $replacement = rtrim($set, ', ');
                    }
                } elseif ($specifier === '#') {
                    if (!is_string($param) && !is_array($param)) {
                        throw new Exception('Expected parameter');
                    } elseif (is_string($param)) {
                        $replacement = '`' . addslashes($param) . '`';
                    } else {
                        foreach ($param as $key => $value) {
                            $param[$key] = '`' . addslashes($value) . '`';
                        }
                        $replacement = implode(', ', $param);
                    }


                }

                $query = substr_replace($query, $replacement, $pos, 2);
            } else {
                $pos = strpos($query, '?');
                if (is_null($param)) {
                    $replacement = 'NULL';
                } elseif (is_bool($param)) {
                    $replacement = $param ? '1' : '0';
                } elseif (is_float($param)) {
                    $replacement = (string)$param;
                } elseif (is_string($param)) {
                    $replacement = '\'' . addslashes($param) . '\'';
                } else {
                    throw new Exception('Unsupported parameter type: ' . gettype($param));
                }

                $query = substr_replace($query, $replacement, $pos, 1);
            }
        }

        return $query;

    }

    public function skip()
    {
        return false;
    }
}
