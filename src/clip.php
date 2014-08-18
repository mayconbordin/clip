<?php

require_once 'colors.php';

function isWhiteSpace($c)
{
    return preg_match('/\s/', $c);
}

function isOptional($c)
{
    return preg_match('/[\[\]]/', $c);
}

function isOpenOptional($c)
{
    return preg_match('/[\[]/', $c);
}

function isCloseOptional($c)
{
    return preg_match('/[\]]/', $c);
}

function isOpenRequired($c)
{
    return preg_match('/[\(]/', $c);
}

function isCloseRequired($c)
{
    return preg_match('/[\)]/', $c);
}

function isArgument($c)
{
    return preg_match('/[<>]/', $c);
}

function isUpperCase($c)
{
    return preg_match('/[A-Z]/', $c);
}

function isStatic($c)
{
    return preg_match('/[A-Za-z0-9]/', $c);
}

function isDash($c)
{
    return $c == '-';
}

function isSingleDash($str)
{
    preg_match("/\-.*/", $str, $matches);
    return count($matches) > 0;
}

function isDoubleDash($str)
{
    preg_match("/\-\-.*/", $str, $matches);
    return count($matches) > 0;
}

function isOption($c)
{
    return !isOptional($c) && !isWhiteSpace($c) && !isEqual($c);
}

function isEqual($c)
{
    return $c == '=';
}

function findToken($tokens, $name)
{
    foreach ($tokens as $token) {
        if ($token['name'] == $name) {
            return $token;
        }
    }
    
    return null;
}

class Lexer
{
    protected $c;
    protected $tokens;
    protected $i = -1;
    protected $input;
    protected $length;
    
    protected $optional = 0;
    protected $required = 0;
    
    public function __construct($input)
    {
        $this->input = $input;
        $this->length = strlen($input);
    }
    
    protected function next()
    {
        $this->i++;
        $this->c = isset($this->input[$this->i]) ? $this->input[$this->i] : null;
        return $this->c;
    }
    
    protected function prev()
    {
        $this->i--;
        $this->c = isset($this->input[$this->i]) ? $this->input[$this->i] : null;
        return $this->c;
    }
    
    protected function fetchArgument($optional = false)
    {
        $arg = '';
        while (!isArgument($this->next()))
            $arg .= $this->c;
        $this->addArgument($arg, $optional);
    }
    
    protected function fetchStatic()
    {
        $arg = '';
        while (isStatic($this->c) && !isWhiteSpace($this->c)) {
            $arg .= $this->c;
            $this->next();
        }
        
        $this->addStatic($arg);
    }

    public function tokenize()
    {
        while ($this->i < $this->length) {
            if (isWhiteSpace($this->c) || $this->c === null) {
                //continue;
            } else if (isOpenOptional($this->c)) {
                $this->optional++;
            } else if (isCloseOptional($this->c)) {
                $this->optional--;
            } else if (isOpenRequired($this->c)) {
                $this->required++;
            } else if (isCloseRequired($this->c)) {
                $this->required--;
            } else if (isArgument($this->c)) {
                $this->fetchArgument($this->optional > 0);
            } else if (isDash($this->c)) {
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

                if (isEqual($this->c) || ($this->i+1 < $this->length 
                   && isArgument($this->input[$this->i+1]))
                ) {
                    $this->next();
                    $var = '';
                    
                    while ($this->next() && !isArgument($this->c))
                        $var .= $this->c;
                    
                    if (strlen($var) == 0)
                        throw new Exception('Invalid option syntax.');
                        
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
    
    public function addStatic($name)
    {
        $this->tokens[] = array(
            'type'     => 'static',
            'name'     => $name,
            'optional' => false,
            'regex'    => "$name"
        );
    }
    
    public function addArgument($name, $optional = false)
    {
        $this->tokens[] = array(
            'type'     => 'argument',
            'name'     => $name,
            'optional' => $optional,
            'regex'    => "[A-Za-z0-9]+"
        );
    }
    
    public function addOption($name, $var, $optional = false, $double = false)
    {
        $this->tokens[] = array(
            'type'     => 'option',
            'name'     => $name,
            'var'      => $var,
            'optional' => $optional,
            'double'   => $double,
            'regex'    => $this->getFlagOptionRegex($name, $var, $optional, $double)
        );
    }
    
    public function addFlag($name, $optional = false, $double = false)
    {
        $this->tokens[] = array(
            'type'     => 'flag',
            'name'     => $name,
            'optional' => $optional,
            'double'   => $double,
            'regex'    => $this->getFlagOptionRegex($name, null, $optional, $double)
        );
    }
    
    protected function getFlagOptionRegex($name, $var, $optional, $double)
    {
        $regex = "\-" . (($double === true) ? "\-" : "") . $name;
        
        if ($var !== null) {
            if ($double === true) $regex .= "=[A-Za-z0-9\.\\/\-\_]+";
            else $regex .= "\s?[A-Za-z0-9\.\\/\-\_]+";
        }

        return $regex;
    }
}

class Arguments
{
    public function get($name, $default = null)
    {
        if (isset($this->$name)) {
            return $this->$name;
        } else {
            return $default;
        }
    }
}

class Clip
{
    protected $commands = array();
    
    public function register($route, $fn)
    {
        $lexer = new Lexer($route);
        $tokens = $lexer->tokenize();
        
        $this->commands[] = array(
            'tokens'   => $tokens,
            'callback' => $fn,
            'route'    => $route
        );
    }
    
    public function run($argv)
    {
        $argv  = array_slice($argv, 1);
        $route = join(" ", $argv);
        $best  = $this->match($route);
        
        if ($best !== null) {
            $tokens  = $this->associativeTokens($best['command']['tokens']);
            $matches = $this->parseMatches($best['matches']);
            $args    = new Arguments();
            
            foreach ($tokens as $key => $token) {
                $value = null;
                
                if ($token['type'] == 'flag')
                    $value = isset($matches[$key]);
                else
                    $value = isset($matches[$key]) ? $matches[$key] : null;
                    
                if ($token['type'] == 'option')
                    $key = $token['var'];
                    
                $args->$key = $value;
            }
            
            call_user_func_array($best['command']['callback'], array($args));
        } else {
            self::error("Command not found!\n");
            $this->usage();
        }
    }
    
    public function match($route)
    {
        $best = null;
        foreach ($this->commands as $command) {
            $regex = "";
            $opt = false;
            $optRegex = "";
            $optCount = 0;
            
            foreach ($command['tokens'] as $index => $token) {
                $name = str_replace("-", "", $token['name']);
                
                if ($opt === false && $token['optional'] === true) {
                    $opt = true;
                }
                
                if ($opt === true && ($token['optional'] === false 
                   || $index == (count($command['tokens']) - 1))
                ) {
                    if ($token['optional'] === true) {
                        $optRegex .= "(?<" . $name . "___NUM__> "
                                  .  $token['regex'] . ")|";
                        $optCount++;
                    }
                
                    $regex = substr($regex, 0, -1);
                    
                    for ($i = 0; $i < $optCount; $i++) {
                        $regex .= "(".str_replace("__NUM__", $i, $optRegex).")?";
                    }
                    
                    $regex .= " ";
                    
                    $opt = false;
                    $optRegex = "";
                    $optCount = 0;
                }
                
                if ($opt === false && $token['optional'] === false) {
                    $regex .= "(?<" . $name . "_>" . $token['regex'] . ") ";
                } else {
                    $optRegex .= "(?<".$name."___NUM__> ".$token['regex'].")|";
                    $optCount++;
                }              
            }
            
            $regex = substr($regex, 0, -1);
            preg_match("/".$regex."/", $route, $matches);
            
            if (count($matches) > count($best['matches'])) {
                $best = array(
                    'matches' => array_slice($matches, 1),
                    'command' => $command
                );
            }
        }
        
        return $best;
    }
    
    public function usage()
    {
        $str = "Usage:\n";
        
        foreach ($this->commands as $command) {
            $str .= "  " . $command['route'] . "\n";
        }
        
        echo $str;
    }
    
    private function associativeTokens($tokens)
    {
        $assoc = array();
        
        foreach ($tokens as $token) {
            $name = str_replace("-", "", $token['name']);
            $assoc[$name] = $token;
        }
        
        return $assoc;
    }
    
    private function parseMatches($matches)
    {
        $args = array();
        
        foreach ($matches as $key => $match) {
            $value = trim($match);
            $pos = stripos($key, "_");

            if ($pos !== false && $value !== null && $value != "") {
                $key = substr($key, 0, $pos);
                
                if (isDoubleDash($value)) {
                    $values = explode("=", $value);
                } else if (isSingleDash($value)) {
                    $values = explode("-".$key, $value);
                } else {
                    $values = explode(" ", $value);
                }
                
                if (count($values) == 2) {
                    $value = trim($values[1]);
                }
                
                $args[$key] = $value;
            }
        }
        
        return $args;
    }
    
    public static function println()
    {
        $args = func_get_args();
        echo call_user_func_array('sprintf', $args) . "\n";
    }
    
    public static function printf()
    {
        $args = func_get_args();
        echo call_user_func_array('sprintf', $args);
    }
    
    public static function bool()
    {
        $args = func_get_args();
        echo call_user_func_array('sprintf', $args) . " [yes/no]\n";
        $handle = fopen ("php://stdin","r");
        $line = fgets($handle);
        if (!in_array(trim($line), array('yes', 'y'))) {
            return false;
        }
        return true;
    }
    
    public static function success()
    {
        $args = func_get_args();
        self::color('green', $args);
    }
    
    public static function error()
    {
        $args = func_get_args();
        self::color('red', $args);
    }
    
    private static function color($name, $arguments)
    {
        $color = new Colors();
        $str = "";
        if (in_array($name, $color->getForegroundColors())) {
            $str = call_user_func_array('sprintf', $arguments);
            $str = $color->getColoredString($str, $name);
        }
        
        echo $str . "\n";
    }
}
