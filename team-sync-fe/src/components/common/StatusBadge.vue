<script setup>
import {
    getSkillLevelBadgeClass,
    getStatusBadgeClass,
    getStatusColor,
    getPriorityColor,
    getProjectStatusColor,
    getLeaveTypeBadgeClass,
    getLeaveRequestStatusBadgeClass,
    getTaskStatusBadgeClass,
    getPayrollStatusColor,
} from "@/utils/badgeUtils";
import { capitalize } from "@/utils/formatUtils";

const props = defineProps({
    /**
     * The status/type value to display
     */
    value: {
        type: String,
        required: true,
    },
    /**
     * Badge type determines which color mapping to use:
     * 'status' | 'skill' | 'priority' | 'project' | 'leave-type' | 'leave-status' | 'task' | 'payroll' | 'team'
     */
    type: {
        type: String,
        default: "status",
    },
    /**
     * Custom label (overrides auto-capitalized value)
     */
    label: {
        type: String,
        default: "",
    },
});

const typeMap = {
    status: getStatusBadgeClass,
    skill: getSkillLevelBadgeClass,
    priority: getPriorityColor,
    project: getProjectStatusColor,
    "leave-type": getLeaveTypeBadgeClass,
    "leave-status": getLeaveRequestStatusBadgeClass,
    task: getTaskStatusBadgeClass,
    payroll: getPayrollStatusColor,
    team: getStatusColor,
};

const getBadgeClass = () => {
    const fn = typeMap[props.type] || getStatusBadgeClass;
    return fn(props.value);
};

const displayLabel = () => {
    return props.label || capitalize(props.value);
};
</script>

<template>
    <span :class="[getBadgeClass(), 'px-2 py-1 rounded-md text-xs font-semibold capitalize']">
        {{ displayLabel() }}
    </span>
</template>
