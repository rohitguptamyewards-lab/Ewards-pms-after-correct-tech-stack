<?php
namespace App\Enums;

enum ProjectStage: string
{
    case DocYetToStart = 'doc_yet_to_start';
    case GptChatWip = 'gpt_chat_wip';
    case DocWip = 'doc_wip';
    case FigmaYetToStart = 'figma_yet_to_start';
    case FigmaDesignWip = 'figma_design_wip';
    case DocUnderReview = 'doc_under_review';
    case DocChangesRequired = 'doc_changes_required';
    case DevYetToStart = 'dev_yet_to_start';
    case DevelopmentWip = 'development_wip';
    case TestingYetToStart = 'testing_yet_to_start';
    case TestingWip = 'testing_wip';
    case DevTestingWip = 'dev_testing_wip';
    case ReadyForInternal = 'ready_for_internal';
    case TestingWipInternal = 'testing_wip_internal';
    case BugsReportedTestInternal = 'bugs_reported_test_internal';
    case BugsReportedTesting = 'bugs_reported_testing';
    case BugsReportedInternal = 'bugs_reported_internal';
    case LiveTestingYetToStart = 'live_testing_yet_to_start';
    case ReadyToGoForLive = 'ready_to_go_for_live';
    case LiveTestingWip = 'live_testing_wip';
    case BugsReportedLive = 'bugs_reported_live';
    case BugFixingWip = 'bug_fixing_wip';
    case BugsFixed = 'bugs_fixed';
    case DevChangesRequired = 'dev_changes_required';
    case Live = 'live';
    case NeedToDiscuss = 'need_to_discuss';
    case OnHold = 'on_hold';
    case Scrapped = 'scrapped';
    case Rnd = 'rnd';
    case YetToAnnounceOnGroup = 'yet_to_announce_on_group';
    case YetToPutOnCron = 'yet_to_put_on_cron';
    case TicketRaisedForRevenue = 'ticket_raised_for_revenue';
    case DesignUnderReview = 'design_under_review';
    case VibeCodeShared = 'vibe_code_shared';

    public function label(): string
    {
        return match($this) {
            self::DocYetToStart => 'Doc - Yet to Start',
            self::GptChatWip => 'GPT Chat - WIP',
            self::DocWip => 'Doc - WIP',
            self::FigmaYetToStart => 'Figma - Yet to Start',
            self::FigmaDesignWip => 'Figma Design - WIP',
            self::DocUnderReview => 'Doc - Under Review',
            self::DocChangesRequired => 'Doc Changes Required',
            self::DevYetToStart => 'Dev - Yet to Start',
            self::DevelopmentWip => 'Development - WIP',
            self::TestingYetToStart => 'Testing - Yet to Start',
            self::TestingWip => 'Testing - WIP',
            self::DevTestingWip => 'Dev + Testing - WIP',
            self::ReadyForInternal => 'Ready for Internal',
            self::TestingWipInternal => 'Testing - WIP Internal',
            self::BugsReportedTestInternal => 'Bugs Reported - Test Internal',
            self::BugsReportedTesting => 'Bugs Reported + Testing',
            self::BugsReportedInternal => 'Bugs Reported Internal',
            self::LiveTestingYetToStart => 'Live Testing - Yet to Start',
            self::ReadyToGoForLive => 'Ready To Go for LIVE',
            self::LiveTestingWip => 'Live Testing - WIP',
            self::BugsReportedLive => 'Bugs Reported - Live',
            self::BugFixingWip => 'Bug Fixing - WIP',
            self::BugsFixed => 'Bugs Fixed',
            self::DevChangesRequired => 'Dev-Changes Required',
            self::Live => 'LIVE',
            self::NeedToDiscuss => 'Need to Discuss',
            self::OnHold => 'On Hold',
            self::Scrapped => 'Scrapped',
            self::Rnd => 'R&D',
            self::YetToAnnounceOnGroup => 'Yet to Announce on Group',
            self::YetToPutOnCron => 'Yet to Put on Cron',
            self::TicketRaisedForRevenue => 'Ticket Raised for Revenue',
            self::DesignUnderReview => 'Design - Under Review',
            self::VibeCodeShared => 'Vibe Code Shared',
        };
    }

    public function dotColor(): string
    {
        return match($this) {
            self::DocYetToStart => '#1a1a2e',
            self::GptChatWip => '#f0c929',
            self::DocWip => '#c9a0dc',
            self::FigmaYetToStart => '#27ae60',
            self::FigmaDesignWip => '#85c1e9',
            self::DocUnderReview => '#2e86c1',
            self::DocChangesRequired => '#e67e22',
            self::DevYetToStart => '#aed6f1',
            self::DevelopmentWip => '#2e86c1',
            self::TestingYetToStart => '#b7950b',
            self::TestingWip => '#e67e22',
            self::DevTestingWip => '#2e86c1',
            self::ReadyForInternal => '#1abc9c',
            self::TestingWipInternal => '#8e44ad',
            self::BugsReportedTestInternal => '#e74c3c',
            self::BugsReportedTesting => '#c0392b',
            self::BugsReportedInternal => '#c0392b',
            self::LiveTestingYetToStart => '#85c1e9',
            self::ReadyToGoForLive => '#82e0aa',
            self::LiveTestingWip => '#27ae60',
            self::BugsReportedLive => '#e74c3c',
            self::BugFixingWip => '#f0c929',
            self::BugsFixed => '#c9a0dc',
            self::DevChangesRequired => '#85c1e9',
            self::Live => '#145a32',
            self::NeedToDiscuss => '#2e86c1',
            self::OnHold => '#f0c929',
            self::Scrapped => '#b7950b',
            self::Rnd => '#6e2c00',
            self::YetToAnnounceOnGroup => '#f9e79f',
            self::YetToPutOnCron => '#c9a0dc',
            self::TicketRaisedForRevenue => '#f5b7b1',
            self::DesignUnderReview => '#f5b7b1',
            self::VibeCodeShared => '#27ae60',
        };
    }
}
