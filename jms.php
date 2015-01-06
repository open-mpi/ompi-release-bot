<?php

/*
Gilles: some suggestions...

Instead of exploding the body into lines and strcasecmping for the
special tokens, how about using perl regexps?

See jms_process(), a template for checking for each of the 6 tokens
(label, nolabel, milestone, nomilestone, assign, noassign).  Each sub
function checks for one type of token (for simplicity).

There's a number of sub-functions that would need to be written, such
as: set_issue_label(), remove_issue_label(), add_issue_comment(),
...etc.  For each of these functions, I like your idea of queueing up
all the actions and then processing them at the end.  E.g.,
set_issue_label() might well just add the action of setting a label on
an issue to some array that is then processed via handle_all_actions()
at the end of jms_process().

Also, how about this concept: the bot replies via comment on the issue
if it fails for some reason.  E.g., if someone asks for a label that
doesn't exist, the bot adds a comment on the issue saying "You asked
for a label that doesn't exist."  This gives feedback to users about
their error.

We might want to put some kind of filter in to prevent the bot from
processing its own comments (there should never be a problem with the
bot processing its own comments, but just in principle, we should have
some kind of escape hatch saying "if this is a new comment from me,
then exit(0)".

*/

function jms_process($org, $repo, $issue_num, $body)
{
    print "Checking body: $body\n\n";

    find_label($org, $repo, $issue_num, $body);
    find_nolabel($org, $repo, $issue_num, $body);
    find_milestone($org, $repo, $issue_num, $body);
    find_nomilestone($org, $repo, $issue_num, $body);
    find_assign($org, $repo, $issue_num, $body);
    find_noassign($org, $repo, $issue_num, $body);

    handle_all_actions();
}

#
# Search for label:<name>
#
function find_label($org, $repo, $issue_num, $body)
{
    if (0 == preg_match_all("/label:(\S+)/m", $body, $matches)) {
        return;
    }

    foreach ($matches[1] as $label) {
        if (!label_exists($org, $repo, $label)) {
            add_comment($org, $repo, $issue_num,
                        "OMPIBot error: Label $label does not exist");
        } else {
            set_issue_label($org, $repo, $issue_num, $label);
        }
    }
}

#
# Search for nolabel:<name>
#
function find_nolabel($org, $repo, $issue_num, $body)
{
    if (0 == preg_match_all("/nolabel:(\S+)/m", $body, $matches)) {
        return;
    }

    foreach ($matches[1] as $label) {
        # JMS Error if the label is not already set on this issue,
        # or does not exist
        if (!label_set_on_issue($org, $repo, $issue_num, $label)) {
            add_comment($org, $repo, $issue_num,
                        "OMPIBot error: Label $label is not set on issue $issue_num");
        } else if (!label_exists($org, $repo, $label)) {
            add_comment($org, $repo, $issue_num,
                        "OMPIBot error: Label $label does not exist");
        } else {
            remove_issue_label($org, $repo, $issue_num, $label);
        }
    }
}

#
# Search for milestone:<name>
#
function find_milestone($org, $repo, $issue_num, $body)
{
    if (0 == preg_match_all("/milestone:(\S+)/m", $body, $matches)) {
        return;
    }

    if (count($matches[1]) == 1) {
        $milestone = $matches[1][0];

        # JMS Error if the milestone does not exist
        if (!milestone_exists($org, $repo, $milestone)) {
            add_comment($org, $repo, $issue_num,
                        "OMPIBot error: Milestone $milestone does not exist");
        } else {
            # JMS It's ok to override a milestone that was already
            # set
            set_issue_milestone($org, $repo, $issue_num, $milestone);
        }
    } else if (count($matches[1]) > 1) {
        add_comment($org, $repo, $issue_num,
                    "OMPIBot error: Cannot set more than one milestone on an issue");
    }
}

#
# Search for nomilestone:<name>
#
function find_nomilestone($org, $repo, $issue_num, $body)
{
    if (0 == preg_match_all("/nomilestone:(\S+)/m", $body, $matches)) {
        return;
    }

    if (count($matches[1]) == 1) {
        $milestone = $matches[1][0];

        # JMS Error if the milestone is not already set on the issue
        if (current_milestone($org, $repo, $issue_num) <> $milestone) {
            add_comment($org, $repo, $issue_num,
                        "OMPIBot error: Milestone $milestone is not set on issue $issue_num");
        } else {
            remove_issue_milestone($org, $repo, $issue_num, $milestone);
        }
    } else if (count($matches[1]) > 1) {
        add_comment($org, $repo, $issue_num,
                    "OMPIBot error: Cannot remove more than one milestone from an issue");
    }
}

#
# Search for assign:<name>
#
function find_assign($org, $repo, $issue_num, $body)
{
    if (0 == preg_match_all("/assign:(\S+)/m", $body, $matches)) {
        return;
    }

    if (count($matches[1]) == 1) {
        $user = $matches[1][0];

        # JMS Error if the user does not exist or is not part of
        # this organization
        if (!valid_user($org, $repo, $user)) {
            add_comment($org, $repo, $issue_num,
                        "OMPIBot error: User $user is not valid for issue $issue_num");
        } else {
            # JMS It's ok to override a user that was already assigned
            set_issue_assignee($org, $repo, $issue_num, $user);
        }
    } else if (count($matches[1]) > 1) {
        add_comment($org, $repo, $issue_num,
                    "OMPIBot error: Cannot assign more than one user on an issue");
    }
}

#
# Search for noassign:<name>
#
function find_noassign($org, $repo, $issue_num, $body)
{
    if (0 == preg_match_all("/assign:(\S+)/m", $body, $matches)) {
        return;
    }

    if (count($matches[1]) == 1) {
        $assign = $matches[1][0];

        # JMS Error if the user is not already set on the issue
        if (current_assign($org, $repo, $issue_num) <> $user) {
            add_comment($org, $repo, $issue_num,
                        "OMPIBot error: User $user is not assigned to issue $issue_num");
        } else {
            remove_issue_assignee($org, $repo, $issue_num, $user);
        }
    } else if (count($matches[1]) > 1) {
        add_comment($org, $repo, $issue_num,
                    "OMPIBot error: Cannot remove more than one user from an issue");
    }
}



###################################################################
# Main
###################################################################

print "<pre>\n";

$body = "label:first

label:foo
  label:bar  

This is another label:baz.  Hello!

assign:jsquyres
noassign:jsquyres
noassign:bogus

label:last";
jms_process("open-mpi", "ompi-release", "1", $body);
