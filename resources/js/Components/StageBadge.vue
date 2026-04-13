<script setup>
import { computed } from 'vue';

const props = defineProps({
    stage: { type: String, default: '' },
});

const stageMap = {
    doc_yet_to_start: { label: 'Doc - Yet to Start', color: '#1a1a2e' },
    gpt_chat_wip: { label: 'GPT Chat - WIP', color: '#f0c929' },
    doc_wip: { label: 'Doc - WIP', color: '#c9a0dc' },
    figma_yet_to_start: { label: 'Figma - Yet to Start', color: '#27ae60' },
    figma_design_wip: { label: 'Figma Design - WIP', color: '#85c1e9' },
    doc_under_review: { label: 'Doc - Under Review', color: '#2e86c1' },
    doc_changes_required: { label: 'Doc Changes Required', color: '#e67e22' },
    dev_yet_to_start: { label: 'Dev - Yet to Start', color: '#aed6f1' },
    development_wip: { label: 'Development - WIP', color: '#2e86c1' },
    testing_yet_to_start: { label: 'Testing - Yet to Start', color: '#b7950b' },
    testing_wip: { label: 'Testing - WIP', color: '#e67e22' },
    dev_testing_wip: { label: 'Dev + Testing - WIP', color: '#2e86c1' },
    ready_for_internal: { label: 'Ready for Internal', color: '#1abc9c' },
    testing_wip_internal: { label: 'Testing - WIP Internal', color: '#8e44ad' },
    bugs_reported_test_internal: { label: 'Bugs Reported - Test Internal', color: '#e74c3c' },
    bugs_reported_testing: { label: 'Bugs Reported + Testing', color: '#c0392b' },
    bugs_reported_internal: { label: 'Bugs Reported Internal', color: '#c0392b' },
    live_testing_yet_to_start: { label: 'Live Testing - Yet to Start', color: '#85c1e9' },
    ready_to_go_for_live: { label: 'Ready To Go for LIVE', color: '#82e0aa' },
    live_testing_wip: { label: 'Live Testing - WIP', color: '#27ae60' },
    bugs_reported_live: { label: 'Bugs Reported - Live', color: '#e74c3c' },
    bug_fixing_wip: { label: 'Bug Fixing - WIP', color: '#f0c929' },
    bugs_fixed: { label: 'Bugs Fixed', color: '#c9a0dc' },
    dev_changes_required: { label: 'Dev-Changes Required', color: '#85c1e9' },
    live: { label: 'LIVE', color: '#145a32' },
    need_to_discuss: { label: 'Need to Discuss', color: '#2e86c1' },
    on_hold: { label: 'On Hold', color: '#f0c929' },
    scrapped: { label: 'Scrapped', color: '#b7950b' },
    rnd: { label: 'R&D', color: '#6e2c00' },
    yet_to_announce_on_group: { label: 'Yet to Announce on Group', color: '#f9e79f' },
    yet_to_put_on_cron: { label: 'Yet to Put on Cron', color: '#c9a0dc' },
    ticket_raised_for_revenue: { label: 'Ticket Raised for Revenue', color: '#f5b7b1' },
    design_under_review: { label: 'Design - Under Review', color: '#f5b7b1' },
    vibe_code_shared: { label: 'Vibe Code Shared', color: '#27ae60' },
};

const entry = computed(() => stageMap[props.stage] || { label: props.stage || 'No Stage', color: '#aab7b8' });
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700">
        <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="{ backgroundColor: entry.color }" />
        {{ entry.label }}
    </span>
</template>
