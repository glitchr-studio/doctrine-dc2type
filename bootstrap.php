<?php

if (!class_exists("CodeModifier")) {

    class CodeModifier {

        protected $filePath;
        protected $backupFilePath;
        protected $author;
        protected $output;

        protected $recursive;
        protected $tokens = null;
        protected $changes = [];
    
        const T_SINGLE_CHAR = 0;
        const T_MODIFIER_START = -2;
        const T_MODIFIER_META = -3;
        const T_MODIFIER_BLOCK = -4;
        const T_MODIFIER_END = -5;
        
        const PREG_MODIFIER_START = '/^\s*\/\/\s*\[bootstrap:([^\]]+)@([^\]]+)\]\s*##### This code was automatically updated/s';
        const PREG_MODIFIER_END   = '/^\s*\/\/\s*\[bootstrap:([^\]]+)@([^\]]+)\]\s*##### End of modification/s';
        const PREG_MODIFIER_META  = '/^\s*\/\/\s*\[bootstrap:([^\]]+)@([^\]]+)\] (.*)/s';
    
        public function __construct(string $filePath, $author = "unknown", $recursive = False, $output = null) {
    
            $this->filePath = $filePath;
            $this->output = $output ?? $filePath;

            $this->backupFilePath = $filePath . '.bak';
            $this->author = $author;
            $this->recursive = $recursive;
    
            $this->parse();  // Initial parsing of the file content
        }
    
        // Parse the file to identify changes and generate content
        protected function parse() {
    
            $this->tokens = [];
            $this->changes = [];
    
            if ($this->exists()) {
    
                $contents = file_get_contents($this->filePath);
                $this->tokens = $this->tokenize($contents);
                $this->changes = $this->changes($this->tokens);
            }
        }
    
        // Check if the file exists
        protected function exists() {
            return file_exists($this->filePath);
        }
        
        // Backup the current state of the file
        protected function backup() {
    
            if (!file_exists($this->backupFilePath)) {
                copy($this->filePath, $this->backupFilePath);
            }
        }
    
        // Restore the file from the backup
        public function restore() {
    
            if (file_exists($this->backupFilePath)) {
                copy($this->backupFilePath, $this->filePath);
                unlink($this->backupFilePath);
                $this->parse();  // Reparse after restore
            }
        }
    
        protected static function tokenize(string|array $contents) {
    
            $contents = is_array($contents) ? implode(PHP_EOL, $contents) : $contents;
    
            $tokens = [];
    
            $id = 0;
            $modifier = false;
            foreach(token_get_all($contents) as $token) {
    
                if(is_array($token)) {
    
                    $tag = $token[0];
                    $name = is_int($tag) ? token_name($tag) : $tag;
    
                    $id = $token[2] ?? 0;
                    $content = $token[1] ?? "";
                
                } else {
                    
                    $tag = self::T_SINGLE_CHAR;
                    $name = "T_SINGLE_CHAR";
                    $content = $token;
                }
    
                if(preg_match(self::PREG_MODIFIER_META, $content, $matches)) {
                    
                    $tag = self::T_MODIFIER_META;
                    $name = "T_MODIFIER_META";
    
                } else if($modifier) {
    
                    $tag = self::T_MODIFIER_BLOCK;
                    $name = "T_MODIFIER_BLOCK";
                }
    
                if(preg_match(self::PREG_MODIFIER_START, $content, $matches)) {
                    
                    $tag = self::T_MODIFIER_START;
                    $name = "T_MODIFIER_START";
                    $modifier = true;
                }
    
                if(preg_match(self::PREG_MODIFIER_END, $content, $matches)) {
    
                    $tag = self::T_MODIFIER_END;
                    $name = "T_MODIFIER_END";
                    $modifier = false;
                }
    
                $tokens[] = ["id" => $id, "tag" => $tag, "name" => $name, "content" => $content];
            }
    
            return $tokens;
        }
    
        protected static function detokenize(array $tokens, $first = 0, $last = -1)
        {
            if($last < 0) {
                $keys = array_keys($tokens);
                $last = end($keys);
            }
    
            $content = "";
            for($i = $first; $i <= $last; $i++) {
    
                $content .= $tokens[$i]["content"] ?? "";
            }
    
            return $content;
        }
    
        // Fetch the original lines based on the tag
        protected function retrieve($tag, $commit) {
            
            $contents = "";
            foreach ($this->tokens as $token) {
    
                if($token["tag"] == self::T_MODIFIER_META) {
                    if(preg_match(self::PREG_MODIFIER_META, $token["content"], $matches)) {
    
                        if($matches[1] != $tag) continue;
                        if($matches[2] != $commit) continue;
    
                        $contents .= $matches[3].PHP_EOL;
                    }
                }
            }
    
            return rtrim($contents);
        }
    
        // Get the current contents of the file
        public static function filter($tokens, int|array $filter = []) {
    
            $filter = is_array($filter) ? $filter : [$filter];
            
            $filteredTokens = [];
            foreach($tokens as $key => $token) {
    
                if(in_array($token["tag"], $filter)) continue;
                $filteredTokens[$key] = $token;
            }  
    
            return $filteredTokens;
        }

        // Get the current contents of the file
        public static function reduces($tokens, int|array $filter = []) {
    
            $filter = is_array($filter) ? $filter : [$filter];
            
            $filteredTokens = [];
            foreach($tokens as $key => $token) {
    
                if(!in_array($token["tag"], $filter)) continue;
                $filteredTokens[$key] = $token;
            }  
    
            return $filteredTokens;
        }
    
        // Get changes tracked by the class
        public static function changes($tokens) {
    
            $changes = [];
            foreach($tokens as $token) {
    
                if($token["tag"] == self::T_MODIFIER_START) {
    
                    if(preg_match(self::PREG_MODIFIER_META, $token["content"], $matches)) {
    
                        $tag = $matches[1];
                        $commit = $matches[2];
                        
                        $changes[$tag] ??= [];
                        if(in_array($commit, $changes[$tag])) {
                            throw new Exception("Duplicate change '$tag@$commit' already exists.");
                        }
    
                        $changes[$tag][] = $commit;
                    }
                }
            }
    
            return $changes;
        }
    
        // Check if the tag has been modified
        public function has($tag, $commit = null) {
            
            $changes = $this->changes[$tag] ?? [];
            return $commit === null ? count($changes) > 0 : in_array($commit, $changes);
        }
    
        // Get commits based on tag
        public function commits($tag) {
            return $this->changes[$tag] ?? null;
        }
    
        public function write() {
            file_put_contents($this->output, $this->detokenize($this->tokens)); // Save changes back to the file
            $this->parse();  // Reparse after modification
        }
    
        // Print the current contents of the file
        public function print()
        {
            echo "List of changes operated to file: `".$this->filePath."`:<br/>";
            echo "List of tokens:<br/>";
            $padsize = 40;
            foreach ($this->tokens as $token) {
                echo str_pad($token["id"]. ": ".$token["name"]." (". $token["tag"] . ")", $padsize). " \"" . htmlspecialchars(str_replace(PHP_EOL, PHP_EOL.str_pad("", $padsize+2), $token["content"])) . "\"".PHP_EOL;
            }
        }
    
        // Helper method to generate the modification block and save original lines
        protected function generator($tag, $search, $subject, callable $fn, ...$args) {
            
            // Collect the yielded content from the generator function
            $generatedContent = iterator_to_array($fn($tag, $search, $subject, ...$args));

            // If no modification has been made (i.e., the generator yields the same content)
            if (implode(PHP_EOL, $generatedContent) === $subject) {
                // No modification, return the original content unchanged
                return $subject;
            }

            // Otherwise, prepare to add the bootstrap comments and the modifications
            $date = date('Y-m-d');
            $time = date('H:i:s');

            // Generate a unique commit identifier
            $commit = uniqid();
            while ($this->commits($tag, $commit) !== null) {
                $commit = uniqid();
            }

            // Initialize the array for the marked content with the modification comment block
            $markedContent = [];
            $markedContent[] = "// [bootstrap:$tag@$commit] ##### This code was automatically updated by `$this->author` on $date at $time";

            // Add original lines as comments
            $lines = explode(PHP_EOL, $subject);
            foreach ($lines as $line) {
                $markedContent[] = "// [bootstrap:$tag@$commit] " . $line;
            }

            // Add modified lines from the generator function (collected content)
            foreach ($generatedContent as $line) {
                $markedContent[] = $line;
            }

            // End the modification block
            $markedContent[] = "// [bootstrap:$tag@$commit] ##### End of modification";

            // Return the modified content with the bootstrap comments
            return implode(PHP_EOL, $markedContent);
        }
    
        // Rollback the modification to restore the original lines
        public function rollback($tag, $commit = NULL) {
    
            $count = 0;
            if (!$this->has($tag, $commit)) {
                return $count;
            }
    
            $commits = $commit !== null ? [$commit] : $this->commits($tag);
            foreach($commits as $commit) {
    
                if(!$this->has($tag, $commit)) continue;
                $original = $this->retrieve($tag, $commit);
                $count++;
    
                $modifierStartId = -1;
                $modifierStart = -1;
                $modifierEnd = -1;
    
                foreach ($this->tokens as $key => $token) {
    
                    if(!preg_match(self::PREG_MODIFIER_META, $token["content"], $matches)) continue;
                    else if($matches[1] != $tag || $matches[2] != $commit) continue;
                    
                    switch ($token["tag"]) {
    
                        case self::T_MODIFIER_START:
                            $modifierStart = $key;
                            $modifierStartId = $token["id"];
                            break;
    
                        case self::T_MODIFIER_END:
                            $modifierEnd = $key;
                            break;
                    }
                }
    
                if($modifierStart < 0 && $modifierEnd < 0 && $modifierStartId < 0)
                    continue;
    
                $this->tokens[$modifierStart] = [
                    "id" => $modifierStartId,
                    "tag" => T_STRING,
                    "name" => token_name(T_STRING), 
                    "content" => $original
                ];
        
                $keys = range($modifierStart+1, $modifierEnd);
                foreach($keys as $key) {
                    unset($this->tokens[$key]);
                }
            }
    
            $this->write();
            return $count;
        }
    
        public function erase($tag, string|array $search)
        {
            return $this->callback($tag, $search, function($key, $search, $subject) {});
        }
    
        public function prepend($tag, string|array $search, string|array $prepend)
        {
            return $this->callback($tag, $search, 
                fn($key, $search, $subject, $prepend) => yield $prepend . PHP_EOL . $subject, 
                $prepend
            );
        }
    
        public function append($tag, string|array $search, string|array $append)
        {
            return $this->callback($tag, $search, 
                fn($key, $search, $subject, $append) => yield $subject . PHP_EOL . $append,
                $append);
        }
    
        public function prev($token)
        {
            // Get the keys of the tokens array
            $keys = array_keys($this->tokens);
            // Search for the token in the keys
            $currentIndex = array_search($token, $keys);
        
            // Check if the token exists and if there is a previous token
            if ($currentIndex !== false && isset($keys[$currentIndex - 1])) {
                // Return the previous token
                $prevKey = $keys[$currentIndex - 1];
                return $prevKey;
            }
        
            // Return null if no previous token exists
            return null;
        }
    
        public function next($token)
        {
            // Get the keys of the tokens array
            $keys = array_keys($this->tokens);
            // Search for the token in the keys
            $currentIndex = array_search($token, $keys);
        
            // Check if the token exists and if there is a next token
            if ($currentIndex !== false && isset($keys[$currentIndex + 1])) {
                // Return the next token
                $nextKey = $keys[$currentIndex + 1];
                return $nextKey;
            }
        
            // Return null if no next token exists
            return null;
        }
        
        public function replace($tag, string|array $search, string|array $replace)
        {
            return $this->callback($tag, $search, 
                fn($key, $search, $subject, $replace) => yield str_replace($search, $replace[$key] ?? $replace[count($replace) - 1], $subject), 
                is_array($replace) ? $replace : [$replace]
            );
        }
        
        public function replaceInComments($tag, string|array $search, string|array $replace)
        {
            if ($this->has($tag)) {
                return false;
            }
    
            $found = false;
    
            $search  = is_array($search)  ? $search  : [$search];
            foreach ($search as $key => $searchItem) {
    
                foreach($this->tokens as &$token) {

                    if(in_array($token["tag"], [T_COMMENT, T_DOC_COMMENT])) {
                        
                        $content = $this->generator(
                            $tag, $searchItem, $token["content"], 
                            fn($key, $search, $subject, $replace) => yield str_replace($search, $replace[$key] ?? $replace[count($replace) - 1], $subject), 
                            is_array($replace) ? $replace : [$replace]
                        );

                        if ($token["content"] != $content) {
                            $token["content"] = $content;
                            $found = true;
                        }
                    }
                }
            }
    
            if ($found) {

                $this->backup();  // Backup the initial file before saving
                $this->write();
                $this->parse();
            }
    
            return $found;
        }
    
        protected function callback($tag, string|array $search, callable $fn, ...$args)
        {
            if ($this->has($tag)) {
                return false;
            }
    
            $found = false;
    
            $search  = is_array($search)  ? $search  : [$search];
            foreach ($search as $key => $searchItem) {
    
                $nextToken = 0;
    
                do {
    
                    // Clean content to remove comments for matching
                    $tokenTypes = [self::T_MODIFIER_START, self::T_MODIFIER_END, self::T_MODIFIER_META, T_COMMENT, T_DOC_COMMENT];
                    if (!$this->recursive) {
                        $tokenTypes[] = self::T_MODIFIER_BLOCK;
                    }

                    $tokens = $this->filter($this->tokens, $tokenTypes);
                    $offset = $this->offset($tokens, $nextToken);
                    $contents = $this->detokenize($tokens);

                    // Handle multiline search: allow flexible whitespaces between lines
                    $escapedSearch = preg_quote($searchItem, '/');
                    $pattern = '/[^'.PHP_EOL.']*'. str_replace(PHP_EOL, '[ \*]*' . PHP_EOL . '[ ]*', $escapedSearch) . '[^'.PHP_EOL.']*/s';
    
                    // Use preg_match_all to find all occurrences in cleaned content
                    if (!preg_match($pattern, $contents, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                    
                        $nextToken = $this->tell($tokens, $offset+strlen($contents));
                    
                    } else {
    
                        foreach ($matches as $match) {
    
                            $matchStart = $match[1];
                            $matchLength = strlen($match[0]);
    
                            $firstToken = $this->tell($tokens, $matchStart);
                            $lastToken  = $this->tell($tokens, $matchStart + $matchLength - 1);
                            $matchedBlock = $this->detokenize($this->tokens, $firstToken, $lastToken);
                            
                            $prefix = "";
                            if (strpos($matchedBlock, PHP_EOL) === 0) {
                                $matchedBlock = substr($matchedBlock, strlen(PHP_EOL));
                                $prefix = PHP_EOL;
                            }

                            $this->tokens[$firstToken] = [
                                "id" => $this->tokens[$firstToken]["id"],
                                "tag" => T_STRING,
                                "name" => token_name(T_STRING), 
                                "content" => $prefix.$this->generator($tag, $searchItem, $matchedBlock, $fn, ...$args)
                            ];
    
                            $keys = range($firstToken+1, $lastToken);
                            foreach($keys as $key) {
    
                                if(array_key_exists($key, $this->tokens))
                                    unset($this->tokens[$key]);
                            }
                            
                            $found = true;
                            $nextToken = $this->tell($tokens, $matchStart + $matchLength);
                        }
                    }
    
                } while( $nextToken !== null );
            }
    
            if ($found) {
    
                $this->backup();  // Backup the initial file before saving
                $this->write();
                $this->parse();
            }
    
            return $found;
        }
    
        public function prependToLine($tag, string|array $search, string|array $block)
        {
            return $this->replace($tag, $search, $block.PHP_EOL.$search);
        }
    
        public function prependTo($tag, string|array $method, string|array $block)
        {
            return $this->callbackMethod($tag, $method, 
                fn($key, $search, $subject, $block) => yield $block.PHP_EOL.$subject,
                is_array($block)  ? $block  : [$block]
            );
        }
    
        public function appendToLine($tag, string|array $search, string|array $block)
        {
            return $this->replace($tag, $search, $search.PHP_EOL.$block);
        }

        public function appendTo($tag, string|array $method, string|array $block)
        {
            return $this->callbackMethod($tag, $method, 
                fn($key, $search, $subject, $block) => yield $subject.PHP_EOL.$block,
                is_array($block)  ? $block  : [$block]
            );
        }

        private function callbackMethod($tag, string|array $methods, callable $fn, string|array $block)
        {
            $found = 0;
            $methods  = is_array($methods)  ? $methods  : [$methods];
            foreach($methods as $methodId=> $method) {
    
                $methodTokens = $this->findMethod($method);
                $firstToken = $methodTokens[0] ?? false;
                $lastToken = end($methodTokens);
                if($firstToken !== false) {
                
                    $matchedBlock = $this->detokenize($this->tokens, $firstToken, $lastToken);

                    $keys = range($firstToken+1, $lastToken);
                    foreach($keys as $key) {
    
                        if(array_key_exists($key, $this->tokens))
                            unset($this->tokens[$key]);
                    }

                    $this->tokens[$firstToken] = [
                        "id" => $this->tokens[$firstToken]["id"],
                        "tag" => T_STRING,
                        "name" => token_name(T_STRING), 
                        "content" => $this->generator($tag, $method, $matchedBlock, $fn, $block[$methodId])
                    ];
    
                    $this->backup(); // Backup the initial file before saving
                    $this->write();
                    $this->parse();
    
                    $found++;
                }
            }
    
            return $found;
        }
    
        public function findMethod(string $search)
        {
            if(!empty($search)) {
    
                if(!str_starts_with($search, "\\"))
                    $search = "\\".$search;
            }
    
            $tokens = [];
            $brackets = [];
            
            $context = ["namespace" => null, "class" => null, "method" => null];
            foreach($this->tokens as $tokenId => $token) {
    
                $label = ($context["namespace"] === null ? "" : $context["namespace"]) . "\\" .
                         ($context["class"] === null ? "" : $context["class"]."::") .
                         ($context["method"] === null ? "" : $context["method"]);

                if(!str_starts_with($label, "\\"))
                    $label = "\\".$label;
    
                switch($token["tag"]) {
    
                    case T_NAMESPACE:
                        $context["namespace"] = 0;
                        $brackets["namespace"] = 0;
                        $tokens["namespace"] = [$tokenId];
                        break;
    
                    case T_CLASS:
                    case T_ABSTRACT:
                    case T_INTERFACE:
                        $context["class"] = 0;
                        $brackets["class"] = 0;
                        $tokens["class"] = [$tokenId];
                        break;
    
                    case T_FUNCTION:
                        $context["method"] = 0;
                        $brackets["method"] = 0;
                        $tokens["method"] = [$tokenId];
                        break;
                
                    case T_NAME_QUALIFIED:
                    case T_STRING:
                        foreach($context as $key => $_) {
                            if($context[$key] === 0) {
                                $tokens[$key][] = $tokenId;
                                $context[$key] = $token["content"];
                            }
                        }
                        break;
    
                    case self::T_SINGLE_CHAR:
    
                        foreach($brackets as $ctx => $_) {
    
                            switch($token["content"]) {
                                case '{':
                                    $brackets[$ctx]++;
                                    break;
                                case '}':
                                    $brackets[$ctx]--;
    
                                    if($brackets[$ctx] == 0) {
    
                                        $tokens[$ctx][] = $tokenId;
                                        if($label == $search) {
                                            return $tokens[$ctx];
                                        }
    
                                        unset($brackets[$ctx]);
                                        $context[$ctx] = null;
    
                                    }
                                    break;
                            }
                        }
                        break;
    
                    default:
                        foreach($tokens as $ctx => $_) {
                            $tokens[$ctx][] = $tokenId;
                        }
                    }
                }
    
            return [];
        }
    
        public static function offset(array $tokens, string $key) {
    
            $offset = 0;
    
            foreach($tokens as $currentKey => $token) {
                
                if ($currentKey == $key) break;
                $offset += strlen($token["content"]);
            }
    
            return $offset;
        }
    
        public function max_offset() 
        {
            return $this->offset($this->tokens, -1);
        }
    
        public function count() 
        {
            return count($this->tokens);
        }

        public static function read(array $tokens, string $offset)
        {
            $tellp = 0;  // This tracks the position in the token stream

            foreach ($tokens as $token) {
                $length = strlen($token["content"]);  // Get the length of the current token's content

                // Check if the offset falls within the current token
                if ($offset >= $tellp && $offset < $tellp + $length) {
                    // Return the specific character at the given offset within this token
                    $charPosition = $offset - $tellp;
                    return $token["content"][$charPosition];
                }

                // Increment the position tracker by the length of the current token
                $tellp += $length;
            }

            // Return null if the offset is out of bounds
            return null;
        }

        public static function tell(array $tokens, string $offset)
        {
            $tellp = 0;
            foreach($tokens as $key => $token) {
    
                $length = strlen($token["content"]);
                if ($offset >= $tellp && $offset < $tellp+$length) {
                    return $key;
                }
    
                $tellp += $length;
            }
    
            return null;
        }
    }
}
