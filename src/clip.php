<?php

function isWhiteSpace($c) {
    return preg_match('/\s/', $c);
}

function isOptional($c) {
    return preg_match('/[\[\]]/', $c);
}

function isOpenOptional($c) {
    return preg_match('/[\[]/', $c);
}

function isCloseOptional($c) {
    return preg_match('/[\]]/', $c);
}

function isArgument($c) {
    return preg_match('/[<>]/', $c);
}

function isUpperCase($c) {
    return preg_match('/[A-Z]/', $c);
}

function isStatic($c) {
    return preg_match('/[A-Za-z0-9]/', $c);
}

function isDash($c) {
    return $c == '-';
}

function isSingleDash($str) {
    preg_match("/\-.*/", $str, $matches);
    return count($matches) > 0;
}

function isDoubleDash($str) {
    preg_match("/\-\-.*/", $str, $matches);
    return count($matches) > 0;
}

function isOption($c) {
    return !isOptional($c) && !isWhiteSpace($c) && !isEqual($c);
}

function isEqual($c) {
    return $c == '=';
}

function findToken($tokens, $name) {
    foreach ($tokens as $token) {
        if ($token['name'] == $name) {
            return $token;
        }
    }
    
    return null;
}

class Lexer {
    protected $c;
    protected $tokens;
    protected $i = -1;
    protected $input;
    protected $length;
    
    protected $optional = 0;
    
    public function __construct($input) {
        $this->input = $input;
        $this->length = strlen($input);
    }
    
    protected function next() {
        $this->i++;
        $this->c = isset($this->input[$this->i]) ? $this->input[$this->i] : null;
        return $this->c;
    }
    
    protected function prev() {
        $this->i--;
        $this->c = isset($this->input[$this->i]) ? $this->input[$this->i] : null;
        return $this->c;
    }
    
    protected function fetchArgument($optional=false) {
        $arg = '';
        while (!isArgument($this->next()))
            $arg .= $this->c;
        $this->addArgument($arg, $optional);
    }
    
    protected function fetchStatic() {
        $arg = '';
        while (isStatic($this->c) && !isWhiteSpace($this->c)) {
            $arg .= $this->c;
            $this->next();
        }
        $this->addStatic($arg);
    }
    
    protected function fetchLongOption($optional=false) {
        $opt = '';
        while ($this->next() && isOption($this->c)) {
            $opt .= $this->c;
        }
        
        if (isEqual($this->c) || isUpperCase($this->input[$this->i+1])) {
            $var = '';
            while ($this->next() && isUpperCase($this->c))
                $var .= $this->c;
            
            if (strlen($var) == 0)
                throw new Exception('Invalid option syntax. Values should be all UPPER CASE.');
                
            $this->addOption($opt, $var, true);
        } else {
            $this->addFlag($opt, true);
        }
    }
    
    public function tokenize() {
        while ($this->i < $this->length) {
            if (isWhiteSpace($this->c) || $this->c === null) {
                //continue;
            }
            
            else if (isOpenOptional($this->c)) {
                $this->optional++;
            }
            
            else if (isCloseOptional($this->c)) {
                $this->optional--;
            }
            
            else if (isArgument($this->c)) {
                $this->fetchArgument($this->optional > 0);
            }
            
            else if (isDash($this->c)) {
                $this->next();
                
                $double = false;
                
                if (isDash($this->c)) {
                    $double = true;
                } else {
                    $this->prev();
                }
                
                $opt = '';
                while ($this->next() && isOption($this->c)) {
                    $opt .= $this->c;
                }
                
                if (isEqual($this->c) || isUpperCase($this->input[$this->i+1])) {
                    $var = '';
                    while ($this->next() && isUpperCase($this->c))
                        $var .= $this->c;
                    
                    if (strlen($var) == 0)
                        throw new Exception('Invalid option syntax. Values should be all UPPER CASE.');
                        
                    $this->addOption($opt, $var, ($this->optional > 0), $double);
                } else {
                    $this->addFlag($opt, ($this->optional > 0), $double);
                }
            } else {
                $this->fetchStatic();
            }
            
            $this->next();
        }
        
        return $this->tokens;
    }
    
    public function addStatic($name) {
        $this->tokens[] = array(
            'type'     => 'static',
            'name'     => $name,
            'optional' => false,
            'regex'    => "$name"
        );
    }
    
    public function addArgument($name, $optional=false) {
        $this->tokens[] = array(
            'type'     => 'argument',
            'name'     => $name,
            'optional' => $optional,
            'regex'    => "[A-Za-z0-9]+"
        );
    }
    
    public function addOption($name, $var, $optional=false, $double=false) {
        $this->tokens[] = array(
            'type'     => 'option',
            'name'     => $name,
            'var'      => $var,
            'optional' => $optional,
            'double'   => $double,
            'regex'    => $this->getFlagOptionRegex($name, $var, $optional, $double)
        );
    }
    
    public function addFlag($name, $optional=false, $double=false) {
        $this->tokens[] = array(
            'type'     => 'flag',
            'name'     => $name,
            'optional' => $optional,
            'double'   => $double,
            'regex'    => $this->getFlagOptionRegex($name, null, $optional, $double)
        );
    }
    
    protected function getFlagOptionRegex($name, $var, $optional, $double) {
        $regex = "\-" . (($double === true) ? "\-" : "") . $name;
        
        if ($var !== null) {
            if ($double === true) $regex .= "=[A-Za-z0-9\.\\/\-\_]+";
            else $regex .= " [A-Za-z0-9\.\\/\-\_]+";
        }

        return $regex;
    }
    
    protected function buildRegex() {
        
    }
}

class RouteParser extends Lexer {
    public function tokenize() {
        while ($this->i < $this->length) {
            if ($this->c === null) {
                $this->next();
            }
        
            if (isWhiteSpace($this->c)) {
                //continue;
            }
            
            else if (isArgument($this->c)) {
                $this->fetchArgument(false);
            }
            
            else if (isDash($this->c)) {
                $this->next();
                
                $double = false;
                
                if (isDash($this->c)) {
                    $double = true;
                } else {
                    $this->prev();
                }
                
                $opt = '';
                while ($this->next() && isOption($this->c)) {
                    $opt .= $this->c;
                }
                
                if (isEqual($this->c)) {
                    $var = '';
                    while ($this->next() && !isWhiteSpace($this->c))
                        $var .= $this->c;
                    
                    if (strlen($var) == 0)
                        throw new Exception('Invalid option syntax. Values should be all UPPER CASE.');
                        
                    $this->addOption($opt, $var, false, $double);
                } else {
                    $this->addFlag($opt, false, $double);
                }
            } else {
                $this->fetchStatic();
            }
            
            $this->next();
        }
        
        return $this->tokens;
    }
    
    protected function fetchStatic() {
        $arg = '';
        
        while (isStatic($this->c) && !isWhiteSpace($this->c)) {
            $arg .= $this->c;
            $this->next();
        }
        $this->addStatic($arg);
    }
}

class Arguments {
    public function get($name, $default=null) {
        if (isset($this->$name)) {
            return $this->$name;
        } else {
            return $default;
        }
    }
}

class Clip {
    protected $commands = array();
    
    public function register($route, $fn) {
        $lexer = new Lexer($route);
        $tokens = $lexer->tokenize();
        
        $this->commands[] = array('tokens' => $tokens, 'callback' => $fn);
    }
    
    public function run($argv, $argc) {
        $argv = array_slice($argv, 1);
        $parse = array();
        $route = join(" ", $argv);
        
        //$parser = new RouteParser($route);
        //$tokens = $parser->tokenize();
        
        $best = null;
        foreach ($this->commands as $command) {
            $regex = "";
            $opt = false;
            $optRegex = "";
            $optCount = 0;
            
            foreach ($command['tokens'] as $index => $token) {
                if ($opt === false && $token['optional'] === true) {
                    $opt = true;
                } else if ($opt === true && ($token['optional'] === false || $index == (count($command) - 1))) {
                    if ($token['optional'] === true) {
                        $optRegex .= " " . $token['regex'] . "|";
                        $optCount++;
                    }
                
                    $regex = substr($regex, 0, -1) . str_repeat("(" . $optRegex . ")?", $optCount) . " ";
                    $opt = false;
                    $optRegex = "";
                    $optCount = 0;
                }
                
                if ($opt === false && $token['optional'] === false) {
                    $regex .= "(" . $token['regex'] . ") ";
                } else {
                    $optRegex .= " " . $token['regex'] . "|";
                    $optCount++;
                }              
            }
            
            $regex = substr($regex, 0, -1);
            preg_match("/".$regex."/", $route, $matches);
            
            if (count($matches) > count($best['matches'])) {
                $best = array('matches' => array_slice($matches, 1), 'command' => $command);
            }
        }
        
        if ($best !== null) {
            $tokens = $best['command']['tokens'];

            $args = new Arguments();
            foreach ($best['matches'] as $index => $match) {
                $value = trim($match);
                
                if (strlen($value) > 0 && $value[0] == "-") {
                    if (isDoubleDash($value)) {
                        $values = explode("=", $value);
                    } else {
                        $values = explode(" ", $value);
                    }
                    
                    if (count($values) == 2) {
                        $name = str_replace("-", "", $values[0]);
                        $value = $values[1];
                    } else {
                        $name = str_replace("-", "", $value);
                        $value = true;
                    }
                    
                    $args->$name = $value;
                }
            }
        
            call_user_func_array($best['command']['callback'], array($args));
        }
    }
}
