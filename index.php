<?php

class GitHubObject {
    public $available_labels;
    public $available_milestones;
    public $body;
    public $comment;
    public $issue;
    public $labels;
    public $labelsChanged;
    public $org;
    public $payload;
    public $proxy;
    public $repo;
    public $token;
    public $user;
    public $request;

    public function init($org, $user, $repo, $token, $secret) {
        $this->org = $org;
        $this->user = $user;
        $this->repo = $repo;
        $this->token = $token;
        $this->secret = $secret;
        $this->comment = "";
        $this->request = Array();
    }

    public function set_proxy($proxy) {
        $this->proxy = $proxy;
    }

    public function set_payload($payload) {
        $this->payload = $payload;
        $this->labels = Array();
        $this->labelsChanged = false;
        if (isset($payload['issue'])) {
            $this->issue = $payload['issue']['number'];
            foreach ($payload['issue']['labels'] as $label) {
                print_debug("set_payload: " . $label['name'] . "\n");
                $this->labels[count($this->labels)] = $label['name'];
            }
        } elseif (isset($payload['pull_request'])) {
            $this->issue = $payload['pull_request']['number'];
        } else {
            print_debug("not an issue nor a pull_request !\n");
            print_debug(json_encode($payload) . "\n");
        }
    }

    public function set_body_from($from) {
        $this->body = $this->payload[$from]['body'];
    }

    public function add_comment($comment) {
        print_debug($comment."\n");
        $this->comment = $this->comment . $comment . "\n" ;
    }

    /* issue a github comment */
    public function comment_github_issue() {
        print("---\norg=$this->org\nuser=$this->user\nrepo=$this->repo\ntoken=$this->token\n");
        // create curl resource
        $ch = curl_init();

        // set url
        curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/" . $this->user . "/" . $this->repo . "/issues/" . $this->issue . "/comments");

        // set proxy
        if (isset($this->proxy)) curl_setopt($ch, CURLOPT_PROXY, $this->proxy);

        // set header
        $headers = array('User-Agent: curl-php', 'Authorization: token '.$this->token);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // set request
        curl_setopt($ch, CURLOPT_POST, 1);
    
        $comment = array();
        $comment['body'] = $this->comment;
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($comment));

        // return the transfer as string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $output = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        print "POST comment returned " . $httpCode . "\n";
        print "POST comment on issue " . $this->issue . " was :\n" . json_encode($comment) ;

        // close curl resource
        curl_close($ch);
    }

    /* issue a github patch request */
    public function patch_github_issue() {
        print("---\norg=$this->org\nuser=$this->user\nrepo=$this->repo\ntoken=$this->token\n");
        // create curl resource
        $ch = curl_init();

        // set url
        curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/" . $this->user . "/" . $this->repo . "/issues/" . $this->issue);

        // set proxy
        if (isset($this->proxy)) curl_setopt($ch, CURLOPT_PROXY, $this->proxy);

        // set header
        $headers = array('User-Agent: curl-php', 'Authorization: token '.$this->token);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // this is a PATCH request
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');

        // set request
        curl_setopt($ch, CURLOPT_POST, 1);
    
        if ($this->labelsChanged) {
            $this->request['labels'] = array_values($this->labels);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->request));

        // return the transfer as string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $output = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        print "PATCH request returned " . $httpCode . "\n";
        print "PATCH request on issue " . $this->issue . " was :\n" . json_encode($this->request) ;

        // close curl resource
        curl_close($ch);
    }
}

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
function get_github($gh, $request, &$httpCode) {
    global $etag;
    print("---\norg=$gh->org\nuser=$gh->user\nrepo=$gh->repo\ntoken=$gh->token\n");
    // create curl resource
    $ch = curl_init();

    // set url
    print "https://api.github.com/repos/".$gh->user."/".$gh->repo."/".$request."\n";
    print "Authorization: token ".$gh->token."\n";
    if (isset($gh->proxy)) {
        print "PROXY\n";
    } else {
        print "NO PROXY\n";
    }
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/".$gh->user."/".$gh->repo."/".$request);

    // set proxy
    if (isset($gh->proxy)) curl_setopt($ch, CURLOPT_PROXY, $gh->proxy);

    // set header
    $headers = array('User-Agent: curl-php', 'Authorization: token '.$gh->token);
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
    } else {
        print "httpCode = " . $httpCode . "\noutput = " . $output . "\n" ;
    }

    return json_decode($output, true);
}

function is_organization_member($gh, $reviewer) {
    // create curl resource
    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/orgs/".$gh->org."/members/".$reviewer);

    // set proxy
    if (isset($proxy)) curl_setopt($ch, CURLOPT_PROXY, $gh->proxy);

    // set header
    $headers = array('User-Agent: curl-php', 'Authorization: token '.$gh->token);
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

/* return true if the given label exists (case-insensitive search) */
function label_exists ($gh, $label) {
    global $etag;
    if (!isset($gh->available_labels)) {
        if (file_exists("labels.json")) {
            $fd = fopen("labels.json", "r");
            $labels = json_decode(fread($fd, filesize("labels.json")), true);
            $etag = $labels['ETag'];
            fclose($fd);
        } else {
            $labels = Array();
            unset($GLOBALS['etag']);
        }

        $reply = get_github($gh, "labels", $httpCode);

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
        $gh->available_labels = $labels;
    }

    /* Look through the labels (with case-insensitive searching) and
     * see if we can find the desired label */
    foreach ($gh->available_labels as $l => $dummy) {
        if (strcasecmp($l, $label) == 0) {
            return true;
        }
    }
    return false;
}

/* return true if the given milestone exists (case-insensitive
 * search) */
function milestone_exists($gh, $milestone) {
    global $etag;
    if (file_exists("milestones.json")) {
        $fd = fopen("milestones.json", "r");
        $milestones= json_decode(fread($fd, filesize("milestones.json")), true);
        $etag = $milestones['ETag'];
        fclose($fd);
    } else {
        $milestones = Array();
        unset($GLOBALS['etag']);
    }

    $reply = get_github($gh, "milestones", $httpCode);

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
    $gh->available_milestones = $milestones;

    /* Look through the milestones (with case-insensitive searching)
     * and see if we can find the desired milestone */
    foreach ($gh->available_milestones as $m => $n) {
        if (strcasecmp($m, $milestone) == 0) {
            return true;
        }
    }
    return false;
}

/*
 * Case-insensitive search to see if a given label is set
 */
function label_set_on_issue($gh, $label) {
    foreach ($gh->labels as $idx => $name) {
        if (strcasecmp($name, $label) == 0) {
            return true;
        }
    }

    return false;
}

/*
 * Set a (case-insensitive) label on an issue
 */
function set_issue_label($gh, $label) {
    $found = false;
    foreach ($gh->labels as $name => $dummy) {
        if (strcasecmp($name, $label) == 0) {
            print_debug("set_issue_label " . $idx . " => " . $name . "\n");
            $found = true;
            /* Use the actual label name (since this was a
             * case-insensitive search) */
            $label = $name;
        }
    }
    if (!$found) {
        print "set_issue_label: NOT found ".$label."\n";
        $gh->labels[count($gh->labels)] = $label;
        $gh->labelsChanged = true;
    } else {
        print "set_issue_label: FOUND ".$label."\n";
    }
}

/*
 * Remove a (case-insensitive) label on an issue
 */
function remove_issue_label($gh, $label) {
    foreach ($gh->labels as $idx => $name) {
        if (strcasecmp($name, $label) == 0) {
            /* JMS Should this be $idx or $name?  I'm not sure what
             * the keys are and what the values are in gh->labels */
            unset($gh->labels[$idx]);
            $gh->labelsChanged = true;
        }
    }
}

function milestone_set_on_issue($gh) {
    return isset($gh->payload['issue']['milestone']);
}

/*
 * Set a (case-insensitive) label on a milestone
 */
function set_issue_milestone($gh, $milestone) {
    foreach ($gh->available_milestones as $m => $n) {
        if (strcasecmp($m, $milestone) == 0) {
            $gh->request['milestone'] = $n;
            return;
        }
    }
}

function remove_issue_milestone($gh) {
    $gh->request['milestone'] = null;
}

function issue_assigned($gh) {
    return isset($gh->payload['issue']['assignee']);
}

/*
 * No need for case-insensitive searches here; we're assuming that
 * GitHub will treat username "foo" the same as username "Foo".
 */
function set_issue_assignee($gh, $user) {
    $gh->request['assignee'] = $user;
}

function remove_issue_assignee($gh) {
    $gh->request['assignee'] = null;
}

/*
 * Search for label:<name>
 */
function find_label($gh)
{
    /* Hard-coded shortcut: ":+1:" is a shortcut for the "reviewed"/i
     * label (if it exists).  Note that : are \W characters, so we
     * have to use \B here instead of \b. */
    if (preg_match_all("/\B:\+1:\B/m", $gh->body, $matches) > 0 &&
        label_exists($gh, "reviewed")) {
        $gh->body .= "\nlabel:reviewed\n";
    }

    if (0 == preg_match_all("/\blabel:(\S+)\b/m", $gh->body, $matches)) {
        return;
    }

    foreach ($matches[1] as $label) {
        print "handling label ". $label ."\n";
        if (label_set_on_issue($gh, $label)) {
            $gh->add_comment("OMPIBot error: Label $label is already set on issue $gh->issue");
        } else if (!label_exists($gh, $label)) {
            $gh->add_comment("OMPIBot error: Label $label does not exist");
        } else {
            set_issue_label($gh, $label);
        }
    }
}

/*
 * Search for nolabel:<name
 */
function find_nolabel($gh)
{
    /* Hard-coded shortcut: ":-1:" is a shortcut for removing the
     * "reviewed"/i label (if it exists).  Note that : are \W
     * characters, so we have to use \B here instead of \b. */
    if (preg_match_all("/\B:\-1:\B/m", $gh->body, $matches) > 0 &&
        label_exists($gh, "reviewed")) {
        $gh->body .= "\nnolabel:reviewed\n";
    }

    if (0 == preg_match_all("/\bnolabel:(\S+)\b/m", $gh->body, $matches)) {
        return;
    }

    foreach ($matches[1] as $label) {
        if (!label_set_on_issue($gh, $label)) {
            $gh->add_comment("OMPIBot error: Label $label is not set on issue $gh->issue");
        } else if (!label_exists($gh, $label)) {
            $gh->add_comment("OMPIBot error: Label $label does not exist");
        } else {
            remove_issue_label($gh, $label);
        }
    }
}

/*
 * Search for milestone:<name>
 */
function find_milestone($gh)
{
    if (0 == preg_match_all("/\bmilestone:(\S+)\b/m", $gh->body, $matches)) {
        return;
    }

    if (count($matches[1]) == 1) {
        $milestone = $matches[1][0];

        /* JMS Error if the milestone does not exist */
        if (!milestone_exists($gh, $milestone)) {
            $gh->add_comment("OMPIBot error: Milestone $milestone does not exist");
        } else {
            /* JMS It's ok to override a milestone that was already
             * set */
            set_issue_milestone($gh, $milestone);
        }
    } else if (count($matches[1]) > 1) {
        $gh->add_comment("OMPIBot error: Cannot set more than one milestone on an issue");
    }
}

/*
 * Search for nomilestone:
 */
function find_nomilestone($gh)
{
    if (0 == preg_match_all("/\bnomilestone:\B/m", $gh->body, $matches)) {
        return;
    }

    if (count($matches) == 1) {
        /* JMS Error if a milestone is not already set on the issue */
        if (!milestone_set_on_issue($gh)) {
            $gh->add_comment("OMPIBot error: No milestone is set on issue $gh->issue");
        } else {
            remove_issue_milestone($gh);
        }
    } else {
        $gh->add_comment("OMPIBot error: Cannot remove more than one milestone from an issue");
    }
}

/*
 * Search for assign:<name>
 */
function find_assign($gh)
{
    if (0 == preg_match_all("/\bassign:(\S+)\b/m", $gh->body, $matches)) {
        return;
    }

    if (count($matches[1]) == 1) {

        $user = $matches[1][0];
        /* If the username begins with @, strip it off (for
         * convenience). */
        if (preg_match("/^\@/", $user)) {
            $user = substr($user, 1);
        }

        /* JMS Error if the user does not exist or is not part of
         * this organization */
        if (!is_organization_member($gh, $user)) {
            $gh->add_comment("OMPIBot error: User $user is not valid for issue $gh->issue");
        } else {
            /* JMS It's ok to override a user that was already
             * assigned */
            set_issue_assignee($gh, $user);
        }
    } else if (count($matches[1]) > 1) {
        $gh->add_comment("OMPIBot error: Cannot assign more than one user on an issue");
    }
}

/*
 * Search for unassign:
 */
function find_noassign($gh)
{
    if (0 == preg_match_all("/\bunassign:\B/m", $gh->body, $matches)) {
        return;
    }

    if (count($matches) == 1) {
        /* JMS Error if the user is not already set on the issue */
        if (!issue_assigned($gh)) {
            $gh->add_comment("OMPIBot error: No user is assigned to issue $gh->issue");
        } else {
            remove_issue_assignee($gh);
        }
    } else {
        $gh->add_comment("OMPIBot error: Cannot remove more than one user from an issue");
    }
}

function process_comment_body($gh)
{
    print_debug("Checking body: ".$gh->body."\n\n");

    find_label($gh);
    find_nolabel($gh);
    find_milestone($gh);
    find_nomilestone($gh);
    find_assign($gh);
    find_noassign($gh);

    if ($gh->labelsChanged || count($gh->request)>0) {
        $gh->patch_github_issue();
    } else {
        print "NO PATCH\n";
    }

    if (strlen($gh->comment) > 0) {
        $gh->comment_github_issue();
    }
}

/* a comment has been posted to an issue */
function process_issue_comment($gh) {
    if(strcmp($gh->payload['action'], "created") == 0) {
        $gh->set_body_from('comment');
        process_comment_body($gh);
    } else {
        print_debug("nothing to do for action " . $payload['action']);
    }
}

/* an issue has been created */
function process_issues($gh) {
    if(strcmp($gh->payload['action'], "created") == 0) {
        $gh->set_body_from('issue');
        process_comment_body($gh);
    } else {
        print_debug("nothing to do for action " . $payload['action']);
    }
}

/* a pull request has been opened */
function process_pull_request($gh) {
    if(strcmp($gh->payload['action'], "opened") == 0) {
        $gh->set_body_from('pull_request');
        process_comment_body($gh);
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

if (isset($payload['sender']['login']) && (strcmp($payload['sender']['login'],$bot) == 0)) {
    print_debug("Nothing to do: sent by " . $bot . "\n");
    return;
}

$gh = new GitHubObject;
$gh->init($org, $user, $repo, $token, $secret);
if (isset($proxy)) {
    $gh->set_proxy($proxy);
}
$gh->set_payload($payload);

$fn($gh);

?>
