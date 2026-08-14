<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree;

/**
 * What the client does when a recipient list contains a number it cannot parse.
 *
 * The right answer depends entirely on the list. A verification code goes to
 * one person, and sending it to nobody because the number was mistyped is the
 * correct outcome. A notice going out to five hundred customers is a different
 * situation: one bad row in a spreadsheet should not cancel the other 499.
 */
enum InvalidRecipientPolicy: string
{
    /**
     * Reject the whole request. Nothing is sent, nothing is charged, and the
     * exception lists every entry that failed to parse.
     *
     * The default, because silently dropping a recipient is the kind of bug
     * that is only noticed when somebody asks why they never got the message.
     */
    case RejectRequest = 'reject';

    /**
     * Send to the recipients that are valid, and report the rest through
     * {@see SendResult::skippedRecipients()}.
     *
     * A request where *every* recipient is invalid still fails: sending to
     * nobody is never what the caller meant.
     */
    case SkipInvalid = 'skip';
}
