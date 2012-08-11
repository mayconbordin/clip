<?php

function isWhiteSpace($c) {
    return preg_match('/\s/', $c);
}

function isOptional($c) {
    return preg_match('/[\[\]]/', $c);
}

function isArgument($c) {
    return preg_match('/[<>]/', $c);
}

function isUpperCase($c) {
    return preg_match('/[A-Z]/', $c);
}

function isDash($c) {
    return $c == '-';
}

function isOption($c) {
    return !isOptional($c) && !isWhiteSpace($c) && !isEqual($c);
}

function isEqual($c) {
    return $c == '=';
}

class Lexer {
    private $c;
    private $tokens;
    private $i = -1;
    private $input;
    private $length;
    
    public function __construct($input) {
        $this->input = $input;
        $this->length = strlen($input);
    }
    
    private function next() {
        $this->i++;
        return isset($this->input[$this->i]) ? ($this->c = $this->input[$this->i]) : null;
    }
    
    private function prev() {
        $this->i--;
        return isset($this->input[$this->i]) ? ($this->c = $this->input[$this->i]) : null;
    }
    
    private function fetchArgument($optional=false) {
        $arg = '';
        while (!isArgument($this->next()))
            $arg .= $this->c;
        $this->addArgument($arg, $optional);
    }
    
    private function fetchLongOption($optional=false) {
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
            //echo "Current: " . $this->c . "\n"; 
            if (isWhiteSpace($this->c)) {
                $this->next();
                echo "white\n";
            } else if (isOptional($this->c)) {
                echo "optional\n";
                echo $this->c . "\n";
                $this->next();

                if (isArgument($this->c)) {
                    $this->fetchArgument(true);
                }
                
                if (isDash($this->c)) {
                    $this->next();
                    
                    if (isDash($this->c)) {
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
                    } else {
                        $this->prev();
                        $opt = '';
                        while ($this->next() && isOption($this->c)) {
                            $opt .= $this->c;
                        }
                        
                        if (isUpperCase($this->input[$this->i+1])) {
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
                }
                
                $this->next();
            } else if (isArgument($this->c)) {
                $this->fetchArgument();
                $this->next();
            } else {
                $this->next();
            }
        }
        
        return $this->tokens;
    }
    
    public function addArgument($name, $optional=false) {
        $this->tokens[] = array('type' => 'argument', 'name' => $name, 'optional' => $optional);
    }
    
    public function addOption($name, $var, $optional=false) {
        $this->tokens[] = array('type' => 'option', 'name' => $name, 'var' => $var, 'optional' => $optional);
    }
    
    public function addFlag($name, $optional=false) {
        $this->tokens[] = array('type' => 'flag', 'name' => $name, 'optional' => $optional);
    }
}

class Clip {
    public function register($route, $fn) {
        $lexer = new Lexer($route);
        $tokens = $lexer->tokenize();
        
        print_r($tokens);
    }
}

$clip = new Clip();

$clip->register('remote <command> [<name>] [--exclude=PATTERNS] [-f FILE] [--no-prompt] [-s] [<anothername>]', function() {});

/*
$clip->register('remote [-v | --verbose]', function() {});
$clip->register('remote rename <old> <new>', function() {});
$clip->register('remote rm <name>', function() {});
$clip->register('remote set-head <name> (-a | -d | <branch>)', function() {});
$clip->register('remote set-branches <name> [--add] <branch>...', function() {});
$clip->register('remote set-url [--push] <name> <newurl> [<oldurl>]', function() {});
*/
