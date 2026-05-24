<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}
/***
 *      _____        _                      _   _                
 *     |  __ \      | |           ___      | | (_)               
 *     | |  | | __ _| |_ ___     ( _ )     | |_ _ _ __ ___   ___ 
 *     | |  | |/ _` | __/ _ \    / _ \/\   | __| | '_ ` _ \ / _ \
 *     | |__| | (_| | ||  __/   | (_>  <   | |_| | | | | | |  __/
 *     |_____/ \__,_|\__\___|    \___/\/    \__|_|_| |_| |_|\___|
 *                                                               
 *                                                               
 */
function formatDate($time, $displayTime = true) {
    $itemTime = new DateTime($time);
    $lang = $GLOBALS['__pl_active_lang']
        ?? ($_COOKIE['selectedLanguage'] ?? ($GLOBALS['language'] ?? 'cs'));
    $concatWord = $lang === 'en' ? ' at ' : ($lang === 'de' ? ' um ' : ' v ');
    if (!$displayTime) {
        if ($lang === 'en') {
            $formattedDate = $itemTime->format('n/j/Y');
        } else {
            $formattedDate = $itemTime->format('j. n. Y');
        }
        return $formattedDate;
    } else {
        if ($lang === 'en') {
            $date = $itemTime->format('n/j/Y');
            $time = $itemTime->format('g:i A');
            $formattedDate = $date . "<span>" . $concatWord . $time . "</span>";
        } else {
            $date = $itemTime->format('j. n. Y');
            $time = $itemTime->format('H:i');
            $formattedDate = $date . "<span>"  . $concatWord .  $time . "</span>";
        }
        return $formattedDate;
    }
}

/***
 *      ______ _ _                           __ _ _                                 
 *     |  ____(_) |               ___       / _(_) |                                
 *     | |__   _| | ___  ___     ( _ )     | |_ _| | ___ _ __   __ _ _ __ ___   ___ 
 *     |  __| | | |/ _ \/ __|    / _ \/\   |  _| | |/ _ \ '_ \ / _` | '_ ` _ \ / _ \
 *     | |    | | |  __/\__ \   | (_>  <   | | | | |  __/ | | | (_| | | | | | |  __/
 *     |_|    |_|_|\___||___/    \___/\/   |_| |_|_|\___|_| |_|\__,_|_| |_| |_|\___|
 *                                                                                                                                                                    
 */
// Function to get a unique filename to avoid overwriting existing files
function getUniqueFilename($filename, $uploadDir) {
    $counter = 1;
    $originalFilename = $filename;

    while (file_exists($uploadDir . $filename)) {
        $filename = pathinfo($originalFilename, PATHINFO_FILENAME) . '_' . $counter . '.' . pathinfo($originalFilename, PATHINFO_EXTENSION);
        $counter++;
    }

    return $filename;
}

function icon($type, $value) {
    global $default_user_icon, $default_article_img, $url;
    $icon = 'error'; // Default fallback icon if none of the conditions match
    if (!isset($default_user_icon) || !isset($default_article_img) || !isset($url)) {
        return $icon; // Return the default error icon if any required variable is not set
    }
    // Handle the 'user' type
    if ($type === 'user') {
        $user = $value;
        if ($user['username'] === 'default') {
            $icon = $default_user_icon; // Default user icon
        } else {
            $icon = $user['img'] ?? $default_user_icon; // Use default if user image is missing
        }
    } 
    // Handle the 'img' type
    else if ($type === 'img') {
        if ($value) {
            if (!str_starts_with($value, "http")) {
                $icon = $url . "/" . $value; // Concatenate base URL with the image path
            } else {
                $icon = $value; // If the image path is already an absolute URL
            }
        } else {
            if (!str_starts_with($default_article_img, "http")) {
                $icon = $url . "/" . $default_article_img; // Concatenate base URL with the image path
            } else {
                $icon = $default_article_img; // If the image path is already an absolute URL
            }
        }
    }

    // Ensure that $icon is always a string and not null
    return (string)$icon; // Convert to string explicitly if it's not already
}
/***
 *      _____                _ _                 _   _                
 *     |  __ \              | (_)               | | (_)               
 *     | |__) |___  __ _  __| |_ _ __   __ _    | |_ _ _ __ ___   ___ 
 *     |  _  // _ \/ _` |/ _` | | '_ \ / _` |   | __| | '_ ` _ \ / _ \
 *     | | \ \  __/ (_| | (_| | | | | | (_| |   | |_| | | | | | |  __/
 *     |_|  \_\___|\__,_|\__,_|_|_| |_|\__, |    \__|_|_| |_| |_|\___|
 *                                      __/ |                         
 *                                     |___/                          
 */
function countWords($text) {
    // Trim the text and remove punctuation
    if (empty($text) || !is_string($text) || strlen($text) === 0) {
        return 0;
    }
    $trimmedText = trim($text);
    $withoutPunctuation = preg_replace('/[.,\/#!$%\^&\*;:{}=\-_`~()]/', '', $trimmedText);
    
    // Split the text into words
    $words = preg_split('/\s+/', $withoutPunctuation);
    
    // Filter out empty words
    $nonEmptyWords = array_filter($words, function($word) {
        return strlen($word) > 0;
    });
    
    // Return the count of non-empty words
    return count($nonEmptyWords);
}
function calculateReadingTime($wordCount) {
    // Assuming an average reading speed of 175 words per minute
    $wordsPerMinute = 175;

    $time = $wordCount / $wordsPerMinute;
    $seconds = $time * 60;
    $minutes = $seconds / 60;

    // Round minutes and seconds
    $roundedMinutes = floor($minutes);
    $remainingSeconds = $seconds - $roundedMinutes * 60;
    $roundedSeconds = round($remainingSeconds / 15) * 15;

    if ($roundedSeconds === 60) {
        $roundedMinutes += 1;
        $roundedSeconds = 0;
    }

    $remainingSecondsAfterMinutes = $roundedSeconds % 60;

    if ($seconds < 60) {
        return t('editor.reading.less_than_min');
    }
    if ($remainingSecondsAfterMinutes === 0) {
        return $roundedMinutes . ' ' . t('editor.reading.min_short');
    }
    return $roundedMinutes . ' ' . t('editor.reading.min_short')
         . ' ' . $remainingSecondsAfterMinutes . ' ' . t('editor.reading.sec_short');
}
/***
 *      _______   _                               
 *     |__   __| (_)                              
 *        | |_ __ _ _ __ ___  _ __ ___   ___ _ __ 
 *        | | '__| | '_ ` _ \| '_ ` _ \ / _ \ '__|
 *        | | |  | | | | | | | | | | | |  __/ |   
 *        |_|_|  |_|_| |_| |_|_| |_| |_|\___|_|   
 *                                                
 *                                                
 */
// Function to trim content to 150 characters and stop at the first non-p element
function trimContent($content, $limit = 150) {
    // Initialize the DOMDocument to parse the HTML content
    $doc = new DOMDocument();
    libxml_use_internal_errors(true); // Suppress warnings for malformed HTML
    $doc->loadHTML('<?xml encoding="UTF-8">' . $content); // Prefix with an encoding declaration
    $doc->encoding = 'UTF-8';

    // Try to find the first <p> element
    $firstParagraph = null;
    $elements = $doc->getElementsByTagName('p');
    
    if ($elements->length > 0) {
        // Get the first <p> element
        $firstParagraph = $elements->item(0);
    }

    // If there's a <p> element
    if ($firstParagraph) {
        // Get the text content of the first <p> element
        $textContent = $firstParagraph->textContent;

        // Check if the content exceeds the limit
        if (strlen($textContent) > $limit) {
            // Trim the text and add ellipsis
            $textContent = substr($textContent, 0, $limit) . '...';
            
            // Set the trimmed text back to the <p> element
            $firstParagraph->textContent = $textContent;
        }

        // Return the trimmed HTML, ensuring it includes the <p> and </p> tags
        return $doc->saveHTML($firstParagraph);
    } else {
        // If there's no <p> element, remove all HTML elements and return plain text
        $plainText = strip_tags($content);

        // Trim the plain text content
        $trimmedContent = substr($plainText, 0, $limit);

        // If the content was longer than the limit, append ellipsis
        if (strlen($plainText) > $limit) {
            $trimmedContent .= '...';
        }

        return $trimmedContent;
    }
}

/***
 *       ____                        
 *      / __ \                       
 *     | |  | |_   _  ___ _ __ _   _ 
 *     | |  | | | | |/ _ \ '__| | | |
 *     | |__| | |_| |  __/ |  | |_| |
 *      \___\_\\__,_|\___|_|   \__, |
 *                              __/ |
 *                             |___/ 
 */
function pageLink($p, $label, $isActive = false) {
    $q = editQuery(['p'], [$p]); // Set the page number in the query
    $url = '?' . http_build_query($q);
    $class = $isActive ? " class='active'" : '';
    echo "<a$class href='$url'>$label</a>";
}

function editQuery($actions, $ids) {
    global $query;
    // Create a copy of the global $query
    $q = $query;
    // Ensure both inputs are arrays
    if (is_array($actions) && is_array($ids)) {
        foreach ($actions as $index => $action) {
            // Check if a corresponding ID exists for the action
            if (isset($ids[$index])) {
                $q[$action] = $ids[$index];
            }
        }
    }
    // Return the modified copy
    return $q;
}

/***
 *            _   _               
 *           | | | |              
 *       ___ | |_| |__   ___ _ __ 
 *      / _ \| __| '_ \ / _ \ '__|
 *     | (_) | |_| | | |  __/ |   
 *      \___/ \__|_| |_|\___|_|   
 *                                
 *                                
 */
/*function numberOfArticlesWithTag($connection, $tag) {
    // Sanitize the tag to prevent SQL injection
    $sanitizedTag = mysqli_real_escape_string($connection, $tag);

    // Replace "articles" and "tags" with your actual table and column names
    $fetchArticlesQuery = "SELECT COUNT(*) AS count FROM articles WHERE FIND_IN_SET('$sanitizedTag', REPLACE(tags, ', ', ','));";
    $articlesResult = mysqli_query($connection, $fetchArticlesQuery);

    if ($articlesResult) {
        $result = mysqli_fetch_assoc($articlesResult);
        return $result['count'];
    } else {
        // Handle query error as needed
        return false;
    }
}*/

// Function to create clickable tags
function createClickableTags($tags, $page = 'clanky.php') {
    if (!$tags) {
        return '';
    }
    $tagArray = explode(',', $tags);
    $clickableTags = '';
    
    foreach ($tagArray as $tag) {
        $tag = trim($tag);
        $clickableTags .= '<a href="' . $page . '?tag=' . urlencode($tag) . '" class="tag">' . "<span class='iconify-inline' data-icon='ci:tag'></span>". htmlspecialchars($tag) . '</a> ';
    }

    return $clickableTags;
}

function getUserIP() {
    return $_SERVER['REMOTE_ADDR'];
}
?>