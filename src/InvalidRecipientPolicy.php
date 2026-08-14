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
     */
    case RejectRequest = 'reject';

    /**
     * Send to the recipients that are valid, and report the rest through
     * {@see SendResult::skippedRecipients()}.
     *
     * The default. A single recipient that fails to parse still throws,
     * because there is nobody left to send to. The policy only matters when
     * the list has at least one valid number alongside the bad ones.
     */
    case SkipInvalid = 'skip';
}
