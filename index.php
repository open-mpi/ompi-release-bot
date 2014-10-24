<?php

function print_debug ($str) {
    print $str;
}

function HandleHeaderLine( $curl, $header_line ) {
    $subStr = substr($header_line, 0, 7);
    if (strcmp($subStr ,"ETag: \"") == 0) {
        global $etag;
        $etag = substr($header_line, 7, -3);
    }
    return strlen($header_line);
}

/* issue a github get request */
function get_github($request) {
    global $user, $repo, $token, $proxy, $httpCode, $etag;
    // create curl resource
    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/".$user."/".$repo."/".$request);

    // set proxy
    if (isset($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);

    // set header
    $headers = array('User-Agent: curl-php', 'Authorization: token '.$token);
    if (isset($etag)) {
        $headers[2] = 'If-None-Match: "' . $etag . '"';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // return the transfer as string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // handle the received header
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, "HandleHeaderLine");

    // $output contains the output string
    $output = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // close curl resource
    curl_close($ch);

    if ($httpCode != 200 && $httpCode != 304) {
        print "Could not get " . $request . " error " . $httpCode . "\n";
        exit(1);
    }

    return json_decode($output, true);

}

/* issue a github patch request */
function patch_github_issue($issue, $request) {
    global $user, $repo, $token, $proxy, $httpCode, $etag;

    // create curl resource
    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/" . $user . "/" . $repo . "/issues/" . $issue);

    // set proxy
    if (isset($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);

    // set header
    $headers = array('User-Agent: curl-php', 'Authorization: token 3c62c001458b3a86a7511f5e7912107e122b865d');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // this is a PATCH request
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');

    // set request
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));

    // return the transfer as string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // $output contains the output string
    $output = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    print "PATCH request returned " . $httpCode . "\n";
    print "PATCH request on issue " . $issue . " was :\n" . json_encode($request) ;

    // close curl resource
    curl_close($ch);
}

function is_organization_member($org, $reviewer) {
    global $token, $proxy;
    // create curl resource
    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/orgs/".$org."/members/".$reviewer);

    // set proxy
    if (isset($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);

    // set header
    $headers = array('User-Agent: curl-php', 'Authorization: token '.$token);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // return the transfer as string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // $output contains the output string
    $output = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // close curl resource
    curl_close($ch);

    if ($httpCode == 204) {
        return true;
    } else {
        return false;
    }
}

/* return an array of labels */
function get_labels () {
    global $httpCode;
    if (file_exists("labels.json")) {
        $fd = fopen("labels.json", "r");
        $labels = json_decode(fread($fd, filesize("labels.json")), true);
        $etag = $labels['ETag'];
        fclose($fd);
    } else {
        $labels = Array();
    }

    $reply = get_github("labels");

    if ($httpCode == 200) {
        $labels['ETag'] = $etag;
        for($i=0; $i < count($reply); $i++) {
            $labels[$reply[$i]['name']] = 'L';
        }
        $fd = fopen("labels.json", "w") or die ("could not open labels.json\n");
        $output = json_encode($labels);
        fwrite($fd, $output, strlen($output));
        fclose($fd);
    }

    unset($labels['ETag']);
    return $labels;
}

/* return an array of milestones */
function get_milestones () {
    global $httpCode;
    if (file_exists("milestones.json")) {
        $fd = fopen("milestones.json", "r");
        $milestones= json_decode(fread($fd, filesize("milestones.json")), true);
        $etag = $milestones['ETag'];
        fclose($fd);
    } else {
        $milestones = Array();
    }

    $reply = get_github("milestones");


    if ($httpCode == 200) {
        $milestones['ETag'] = $etag;
        for($i=0; $i < count($reply); $i++) {
            $milestones[$reply[$i]['title']] = $reply[$i]['number'];
        }
        $fd = fopen("milestones.json", "w") or die ("could not open milestones.json\n");
        $output = json_encode($milestones);
        fwrite($fd, $output, strlen($output));
        fclose($fd);
    }

    unset($milestones['ETag']);
    return $milestones;
}

function nomilestone ($payload, &$request) {
    if (isset($payload['issue']['milestone'])) {
        print_debug("unsetting milestone " . $payload['issue']['milestone']['title'] . "\n");
        $request['milestone'] = null;
    } else {
        print_debug("already no milestone\n");
    }
}

function setlabels(&$labels, $payload) {
    if (!isset($labels)) {
        $labels = Array();
        foreach ($payload['issue']['labels'] as $label) {
            $labels[count($labels)] = $label['name'];
        }
    }
}

function nolabel(&$labels, &$labelschanged, $payload, $tag) {
    setlabels($labels, $payload);
    $notfound = true;
    foreach ($labels as $idx => $label) {
        if (strcmp($label, $tag) == 0) {
            $notfound = false;
            unset($labels[$idx]);
            $labelschanged = true;
        }
    }
    if ($notfound) print_debug("no such label/milestone to remove : " . $tag . "\n");
}

function process_issue ($payload, $body) {
    global $user, $org, $repo;

    /* is this my repository ? */
    if (strcmp($payload['repository']['full_name'],
               $user . "/" . $repo) != 0) {
        print_debug ("Not my repository : " . $user . "/" . $repo . "\n");
        return;
    }

    $tags = Array();
    $request = Array();
    $sep = " \t";
    $labelschanged = false;


    $lines = explode ("\r\n", $body);
    foreach ($lines as $line) {
        unset($at);
        unset($rev);
        $tok = strtok($line, $sep);
        while ($tok !== false) {
            if ($tok[0] == '@') {
                $at = substr($tok,1);
            } else if ($tok[0] == '#') {
                if (strcasecmp($tok, "#review") == 0 ||
                    strcasecmp($tok, "#assign") == 0) {
                    $rev = true;
                } else if (strcasecmp($tok, "#noassign") == 0) {
                    $rev = false;
                } else if (strcasecmp($tok, "#nomilestone") == 0) {
                    nomilestone($payload, $request);
                } else if (strncasecmp($tok, "#no", 3) == 0) {
                    $tag = substr($tok, 3);
                    if (isset($payload['issue']['milestone']) &&
                        strcmp($payload['issue']['milestone']['title'], $tag) == 0) {
                        nomilestone($payload, $request);
                    } else {
                        nolabel($labels, $labelschanged, $payload, $tag);
                    }
                } else {
                    $tag = substr($tok, 1);
                    if (isset($tags[$tag])) {
                        print_debug("duplicate label/milestone : " . $tag . "\n");
                    } else if (isset($payload['issue']['milestone']) &&
                        strcmp($payload['issue']['milestone']['title'], $tag) == 0) {
                        print_debug("milestone " . $tag . " is already set\n");
                    } else {
                        $found = false;
                        setlabels($labels, $payload);
                        foreach ($labels as $label) {
                            if (strcmp($label, $tag) == 0) {
                                $found = true;
                            }
                        }
                        if ($found) {
                            print_debug("label " . $tag . " is already set\n");
                        } else {
                            $tags[$tag] = true;
                        }
                    }
                }
            }
            if (isset($rev)) {
                if ($rev) {
                    if (isset($at)) {
                        $review = true;
                        $reviewer = $at;
                    }
                } else {
                       $review = false;
                }
            }
            $tok = strtok($sep);
        }
    }

    if (isset($review)) {
        if ($review) {
            if (isset($payload['issue']['assignee'])) {
                if (strcmp($payload['issue']['assignee']['login'], $reviewer) == 0) {
                    print_debug("already assigned to " . $reviewer ."\n");
                } else if (is_organization_member($org, $reviewer)) {
                    $request['assignee'] = $reviewer;
                    print_debug("assigning from " . $payload['issue']['assignee']['login'] . " to " . $reviewer . "\n");
                } else {
                    print_debug("cannot assign to " . $reviewer . " : not a member\n");
                }
            } else if (is_organization_member($org, $reviewer)) {
                $request['assignee'] = $reviewer;
                print_debug("assigning to " . $reviewer . "\n");
            } else {
                print_debug("cannot assign to " . $reviewer . " : not a member\n");
            }
        } else {
            if (isset($payload['issue']['assignee'])) {
                print_debug("unassigning request\n");
                $request['assignee'] = null;
            } else {
                print_debug("already no assignee\n");
            }
        }
    }

    if ($labelschanged || !empty($tags)) {
        if (isset($request['milestone'])) {
            $lm = get_labels();
        } else {
            $repolabels = get_labels();
            $repomilestones = get_milestones();
            $lm = array_merge ($repolabels, $repomilestones);
        }
        foreach ($tags as $tag => $dummy) {
            if (isset($lm[$tag])) {
                if ($lm[$tag] == 'L') {
                    $labels[count($labels)] = $tag;
                    $labelschanged = true;
                } else {
                    $milestone = $lm[$tag];
                }
            }
        }
    }

    if (isset($milestone)) {
        $request['milestone'] = $milestone;
    }

    if ($labelschanged) {
        $request['labels'] = $labels;
    }

    if (!empty($request)) {
        $issue = $payload['issue']['number'];
        patch_github_issue($issue, $request);
    } else {
        print "NO PATCH\n";
    }
}

/* a comment has been posted to an issue */
function process_issue_comment($payload) {
    if(strcmp($payload['action'], "created") == 0) {
        process_issue($payload, $payload['comment']['body']);
    } else {
        print_debug("nothing to do for action " . $payload['action']);
    }
}

/* an issue has been created */
function process_issues($payload) {
    if(strcmp($payload['action'], "created") == 0) {
        process_issue($payload, $payload['issue']['body']);
    } else {
        print_debug("nothing to do for action " . $payload['action']);
    }
}

/* check the existence of the X-Hub-Signature and X-GitHub-Event headers */
if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE']) ||
    !isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
    header("HTTP/1.0 403 Forbidden");
    return;
}

$data = file_get_contents('php://input');
require_once "config.inc";

/* check the request signature */

$ctx = hash_init("sha1", HASH_HMAC, $secret);
hash_update($ctx, $data);
$hash = hash_final($ctx);

if (strcmp("sha1=".$hash, $_SERVER['HTTP_X_HUB_SIGNATURE']) != 0) {
    header("HTTP/1.0 403 Forbidden");
    return;
}

/* the request has been authenticated and can be processed */

header("HTTP/1.1 202 Accepted", true, 202);

$payload = json_decode($data, true);
$event = $_SERVER['HTTP_X_GITHUB_EVENT'];
$fn = "process_" . $event;

if (!function_exists($fn)) {
   print_debug("Nothing to do: unknown event\n");
   return;
}

$fn($payload);

?>
