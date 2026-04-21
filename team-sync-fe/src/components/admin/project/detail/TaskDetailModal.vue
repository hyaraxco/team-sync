<script setup>
import { ref, onMounted, watch, computed } from "vue";
import {
  X,
  User,
  Calendar,
  Tag,
  AlignLeft,
  MessageSquare,
  Paperclip,
  Search,
  SearchX,
  UserCheck,
  ChevronDown,
  Pencil,
  Check,
  Trash2,
} from "lucide-vue-next";
import { debounce } from "lodash";
import StatusBadge from "@/components/common/StatusBadge.vue";
import { formatDate } from "@/utils/dateUtils";
import { useStaffMemberStore } from "@/stores/staffMember";
import { useTaskStore } from "@/stores/task";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import { useToast } from "@/composables/useToast";

const toast = useToast();

const props = defineProps({
  task: {
    type: Object,
    default: null,
  },
  isOpen: {
    type: Boolean,
    default: false,
  },
  projectId: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(["close", "deleted", "assigneeChanged", "updated"]);

const staffMemberStore = useStaffMemberStore();
const { staffMembers } = storeToRefs(staffMemberStore);
const { fetchStaffMembers } = staffMemberStore;

const taskStore = useTaskStore();
const {
  updateTask,
  fetchProjectTasks,
  fetchTaskComments,
  createTaskComment,
  updateTaskComment,
  deleteTaskComment,
  fetchTaskAttachments,
  fetchTaskStatusLogs,
  uploadTaskAttachment,
  deleteTaskAttachment,
} = taskStore;

const authStore = useAuthStore();

const assigneeDropdown = ref(false);
const dueDateEditing = ref(false);
const searchAssignee = ref("");
const selectedAssignee = ref(null);
const editedDueDate = ref("");
const comments = ref([]);
const attachments = ref([]);
const statusLogs = ref([]);
const newComment = ref("");
const editingCommentId = ref(null);
const editingCommentText = ref("");
const isSubmittingComment = ref(false);
const isUploadingAttachment = ref(false);
const commentsLoading = ref(false);
const attachmentsLoading = ref(false);
const statusLogsLoading = ref(false);
const fileInputRef = ref(null);

const roleNames = computed(() =>
  (authStore.user?.roles || []).map((role) => role.name || role)
);

const hasRole = (role) => roleNames.value.includes(role);

const currentEmployeeId = computed(
  () =>
    authStore.user?.employee_profile?.id || authStore.user?.employeeProfile?.id
);

const isOwnAssignedTask = computed(
  () => !!props.task && currentEmployeeId.value === props.task.assignee_id
);

const isProjectLeader = computed(
  () =>
    !!props.task &&
    currentEmployeeId.value === props.task?.project?.leader?.id
);

const normalizedTaskStatus = computed(() => {
  return props.task?.status === "pending" ? "todo" : props.task?.status;
});

const isReviewPhaseLocked = computed(() =>
  ["review", "done"].includes(normalizedTaskStatus.value)
);

const canManageAssignee = computed(
  () =>
    (hasRole("manager") || hasRole("hr") || isProjectLeader.value) &&
    !isReviewPhaseLocked.value
);

const canEditDueDate = computed(
  () => (hasRole("manager") || hasRole("hr")) && !isReviewPhaseLocked.value
);
const canDeleteTask = computed(() => hasRole("manager"));
const canReviewTask = computed(
  () => hasRole("manager") || hasRole("hr") || isProjectLeader.value
);

const isTodoLikeStatus = computed(
  () => props.task?.status === "todo" || props.task?.status === "pending"
);

const canStartTask = computed(
  () =>
    hasRole("staff") &&
    isOwnAssignedTask.value &&
    isTodoLikeStatus.value
);

const canSubmitForReview = computed(
  () =>
    hasRole("staff") &&
    isOwnAssignedTask.value &&
    props.task?.status === "in_progress"
);

const canStartRework = computed(
  () =>
    hasRole("staff") &&
    isOwnAssignedTask.value &&
    props.task?.status === "rejected"
);

const canApproveReview = computed(
  () => canReviewTask.value && props.task?.status === "review"
);

const canRejectReview = computed(
  () => canReviewTask.value && props.task?.status === "review"
);

const canReopenDoneAsRejected = computed(
  () => canReviewTask.value && props.task?.status === "done"
);

const isEmployeeEditableState = computed(() =>
  ["in_progress", "rejected"].includes(normalizedTaskStatus.value)
);

const canCollaborateTask = computed(() => {
  if (hasRole("manager") || hasRole("hr") || isProjectLeader.value) {
    return true;
  }

  if (hasRole("staff")) {
    return isOwnAssignedTask.value && isEmployeeEditableState.value;
  }

  return false;
});

const canMutateEntityOwner = (ownerId) => {
  if (!currentEmployeeId.value || currentEmployeeId.value !== ownerId) {
    return false;
  }

  return (
    !hasRole("staff") ||
    (isOwnAssignedTask.value && isEmployeeEditableState.value)
  );
};

const formatDateTime = (value) => {
  if (!value) {
    return "-";
  }

  try {
    return new Date(value).toLocaleString();
  } catch {
    return String(value);
  }
};

const formatStatusLabel = (status) => {
  return String(status || "-")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
};

const getTaskStatusClass = (status) => {
  const map = {
    pending: "bg-gray-100 text-gray-700",
    todo: "bg-gray-100 text-gray-700",
    in_progress: "bg-blue-100 text-blue-700",
    review: "bg-amber-100 text-amber-700",
    done: "bg-green-100 text-green-700",
    rejected: "bg-red-100 text-red-700",
    cancelled: "bg-slate-100 text-slate-700",
  };

  return map[status] || "bg-gray-100 text-gray-700";
};

const closeModal = () => {
  emit("close");
};

const handleDelete = () => {
  if (confirm("Are you sure you want to delete this task?")) {
    emit("deleted", props.task.id);
    closeModal();
  }
};

const handleUpdateStatus = async (status, extraPayload = {}) => {
  try {
    await updateTask(props.task.id, {
      status,
      ...extraPayload,
    });

    await fetchProjectTasks(props.projectId);

    emit("updated");
  } catch (error) {
    console.error("Failed to update task status:", error);
  }
};

const handleRejectWithReason = async () => {
  const reason = prompt("Reason for rejection/reopen (required):", "");

  if (reason === null) {
    return;
  }

  const trimmedReason = reason.trim();
  if (!trimmedReason) {
    toast.warning("Required", "Rejection reason is required.");
    return;
  }

  await handleUpdateStatus("rejected", {
    rejected_reason: trimmedReason,
  });
};

const loadTaskComments = async () => {
  if (!props.task?.id) {
    comments.value = [];
    return;
  }

  commentsLoading.value = true;
  try {
    comments.value = await fetchTaskComments(props.task.id);
  } catch (error) {
    console.error("Failed to fetch task comments:", error);
  } finally {
    commentsLoading.value = false;
  }
};

const loadTaskAttachments = async () => {
  if (!props.task?.id) {
    attachments.value = [];
    return;
  }

  attachmentsLoading.value = true;
  try {
    attachments.value = await fetchTaskAttachments(props.task.id);
  } catch (error) {
    console.error("Failed to fetch task attachments:", error);
  } finally {
    attachmentsLoading.value = false;
  }
};

const loadTaskCollaborationData = async () => {
  await Promise.all([
    loadTaskComments(),
    loadTaskAttachments(),
    loadTaskStatusLogs(),
  ]);
};

const loadTaskStatusLogs = async () => {
  if (!props.task?.id) {
    statusLogs.value = [];
    return;
  }

  statusLogsLoading.value = true;
  try {
    statusLogs.value = await fetchTaskStatusLogs(props.task.id);
  } catch (error) {
    console.error("Failed to fetch task status logs:", error);
  } finally {
    statusLogsLoading.value = false;
  }
};

const handleCreateComment = async () => {
  const comment = newComment.value.trim();
  if (!comment || !canCollaborateTask.value) {
    return;
  }

  isSubmittingComment.value = true;
  try {
    await createTaskComment(props.task.id, { comment });
    newComment.value = "";
    await loadTaskComments();
  } catch (error) {
    console.error("Failed to create task comment:", error);
  } finally {
    isSubmittingComment.value = false;
  }
};

const startEditComment = (comment) => {
  if (!canMutateEntityOwner(comment.staff_member_id)) {
    return;
  }

  editingCommentId.value = comment.id;
  editingCommentText.value = comment.comment;
};

const cancelEditComment = () => {
  editingCommentId.value = null;
  editingCommentText.value = "";
};

const handleUpdateComment = async (commentId) => {
  const comment = editingCommentText.value.trim();
  if (!comment) {
    return;
  }

  try {
    await updateTaskComment(props.task.id, commentId, { comment });
    cancelEditComment();
    await loadTaskComments();
  } catch (error) {
    console.error("Failed to update task comment:", error);
  }
};

const handleDeleteComment = async (comment) => {
  if (!canMutateEntityOwner(comment.staff_member_id)) {
    return;
  }

  if (!confirm("Delete this comment?")) {
    return;
  }

  try {
    await deleteTaskComment(props.task.id, comment.id);
    await loadTaskComments();
  } catch (error) {
    console.error("Failed to delete task comment:", error);
  }
};

const triggerAttachmentPicker = () => {
  if (!canCollaborateTask.value) {
    return;
  }

  fileInputRef.value?.click();
};

const handleAttachmentSelected = async (event) => {
  const file = event?.target?.files?.[0];
  event.target.value = "";

  if (!file || !canCollaborateTask.value) {
    return;
  }

  isUploadingAttachment.value = true;
  try {
    await uploadTaskAttachment(props.task.id, file);
    await loadTaskAttachments();
  } catch (error) {
    console.error("Failed to upload task attachment:", error);
  } finally {
    isUploadingAttachment.value = false;
  }
};

const handleDeleteAttachment = async (attachment) => {
  if (!canMutateEntityOwner(attachment.staff_member_id)) {
    return;
  }

  if (!confirm("Delete this attachment?")) {
    return;
  }

  try {
    await deleteTaskAttachment(props.task.id, attachment.id);
    await loadTaskAttachments();
  } catch (error) {
    console.error("Failed to delete task attachment:", error);
  }
};

const toggleAssigneeDropdown = () => {
  assigneeDropdown.value = !assigneeDropdown.value;
  if (assigneeDropdown.value) {
    searchAssignee.value = "";
  }
};

const handleSelectAssignee = async (employee) => {
  try {
    await updateTask(props.task.id, {
      assignee_id: staffMember.id,
    });
    selectedAssignee.value = employee;

    // Fetch ulang data tasks
    await fetchProjectTasks(props.projectId);

    emit("assigneeChanged", employee);
    emit("updated");
    assigneeDropdown.value = false;
  } catch (error) {
    console.error("Failed to update assignee:", error);
    assigneeDropdown.value = false;
  }
};

const handleRemoveAssignee = async () => {
  try {
    // Kirim string kosong untuk remove assignee (Laravel lebih baik handle string kosong daripada null)
    await updateTask(props.task.id, {
      assignee_id: "",
    });
    selectedAssignee.value = null;

    // Fetch ulang data tasks
    await fetchProjectTasks(props.projectId);

    emit("assigneeChanged", null);
    emit("updated");
  } catch (error) {
    console.error("Failed to remove assignee:", error);
  }
};

const toggleDueDateEdit = () => {
  dueDateEditing.value = !dueDateEditing.value;
  if (dueDateEditing.value && props.task?.due_date) {
    editedDueDate.value = props.task.due_date;
  }
};

const handleUpdateDueDate = async () => {
  try {
    await updateTask(props.task.id, {
      due_date: editedDueDate.value,
    });

    // Fetch ulang data tasks
    await fetchProjectTasks(props.projectId);

    emit("updated");
    dueDateEditing.value = false;
  } catch (error) {
    console.error("Failed to update due date:", error);
    dueDateEditing.value = false;
  }
};

const cancelDueDateEdit = () => {
  dueDateEditing.value = false;
  editedDueDate.value = props.task?.due_date || "";
};

onMounted(async () => {
  await fetchStaffMembers({
    limit: 6,
    project_id: props.projectId,
  });

  await loadTaskCollaborationData();

  // Set initial assignee from task prop
  if (props.task?.assignee) {
    selectedAssignee.value = props.task.assignee;
  }

  // Set initial due date
  if (props.task?.due_date) {
    editedDueDate.value = props.task.due_date;
  }
});

watch(
  searchAssignee,
  debounce(() => {
    fetchStaffMembers({
      limit: 6,
      search: searchAssignee.value,
      project_id: props.projectId,
    });
  }, 300),
  { deep: true }
);

// Watch for task changes to update selected assignee
watch(
  () => props.task?.assignee,
  (newAssignee) => {
    selectedAssignee.value = newAssignee;
  }
);

// Watch for task changes to update due date
watch(
  () => props.task?.due_date,
  (newDueDate) => {
    if (newDueDate && !dueDateEditing.value) {
      editedDueDate.value = newDueDate;
    }
  }
);

// Watch for entire task object changes
watch(
  () => props.task,
  (newTask) => {
    if (newTask) {
      if (newTask.assignee) {
        selectedAssignee.value = newTask.assignee;
      }
      if (newTask.due_date && !dueDateEditing.value) {
        editedDueDate.value = newTask.due_date;
      }
    }
  },
  { deep: true }
);

watch(
  () => [props.task?.id, props.isOpen],
  async ([taskId, isOpen]) => {
    if (taskId && isOpen) {
      await loadTaskCollaborationData();
    }
  }
);
</script>

<template>
  <ModalWrapper
    :show="isOpen && !!task"
    maxWidth="4xl"
    @close="closeModal"
  >
    <!-- Modal Header -->
    <template #header>
      <div class="flex-1 min-w-0 pr-4">
        <div class="flex items-center gap-3 mb-2">
          <Tag class="w-5 h-5 text-gray-400 shrink-0" />
          <h2 class="text-xl sm:text-2xl font-bold text-gray-900 break-words">
            {{ task.name }}
          </h2>
        </div>
        <p class="text-sm text-gray-500">
          in list <span class="font-semibold">{{ task.status }}</span>
        </p>
      </div>
    </template>

    <!-- Modal Body -->
    <div class="py-2">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <!-- Main Content (Left Side) -->
              <div class="lg:col-span-2 space-y-6">
                <!-- Labels/Priority -->
                <div>
                  <div class="flex items-center gap-2 mb-3">
                    <Tag class="w-4 h-4 text-gray-500" />
                    <h3 class="text-sm font-semibold text-gray-700">Labels</h3>
                  </div>
                  <div class="flex items-center gap-2">
                    <StatusBadge
                      type="priority"
                      :value="task.priority"
                      class="!px-3 !py-1.5 text-sm"
                    />
                  </div>
                </div>

                <!-- Description -->
                <div>
                  <div class="flex items-center gap-2 mb-3">
                    <AlignLeft class="w-4 h-4 text-gray-500" />
                    <h3 class="text-sm font-semibold text-gray-700">
                      Description
                    </h3>
                  </div>
                  <div
                    class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600 leading-relaxed min-h-[100px]"
                  >
                    <p v-if="task.description">{{ task.description }}</p>
                    <p v-else class="text-gray-400 italic">
                      No description added
                    </p>
                  </div>
                </div>

                <!-- Comments -->
                <div>
                  <div class="flex items-center gap-2 mb-3">
                    <MessageSquare class="w-4 h-4 text-gray-500" />
                    <h3 class="text-sm font-semibold text-gray-700">
                      Activity
                    </h3>
                  </div>
                  <div class="space-y-3">
                    <div
                      v-if="commentsLoading"
                      class="text-sm text-gray-500 bg-gray-50 rounded-lg px-3 py-2"
                    >
                      Loading comments...
                    </div>

                    <div
                      v-else-if="comments.length === 0"
                      class="text-sm text-gray-500 bg-gray-50 rounded-lg px-3 py-2"
                    >
                      No comments yet.
                    </div>

                    <div
                      v-for="comment in comments"
                      :key="comment.id"
                      class="border border-gray-100 rounded-lg p-3 bg-white"
                    >
                      <div class="flex items-start justify-between gap-3">
                        <div>
                          <p class="text-sm font-semibold text-gray-900">
                            {{ comment.employee?.user?.name || "Unknown" }}
                          </p>
                          <p class="text-xs text-gray-500">
                            {{ formatDateTime(comment.created_at) }}
                          </p>
                        </div>
                        <div
                          class="flex items-center gap-2"
                          v-if="canMutateEntityOwner(comment.staff_member_id)"
                        >
                          <button
                            type="button"
                            @click="startEditComment(comment)"
                            class="text-gray-400 hover:text-[#0C51D9] transition-colors"
                          >
                            <Pencil class="w-3.5 h-3.5" />
                          </button>
                          <button
                            type="button"
                            @click="handleDeleteComment(comment)"
                            class="text-gray-400 hover:text-red-600 transition-colors"
                          >
                            <Trash2 class="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </div>

                      <div class="mt-2">
                        <template v-if="editingCommentId === comment.id">
                          <textarea
                            v-model="editingCommentText"
                            rows="3"
                            class="w-full border border-gray-200 rounded-lg p-3 text-sm resize-none focus:border-[#0C51D9] focus:ring-2 focus:ring-[#0C51D9] focus:ring-opacity-20 transition-all"
                          ></textarea>
                          <div class="mt-2 flex items-center gap-2">
                            <button
                              type="button"
                              @click="handleUpdateComment(comment.id)"
                              class="px-3 py-1.5 bg-[#0C51D9] text-white rounded-lg text-xs font-medium hover:bg-[#0a42b3] transition-colors"
                            >
                              Save
                            </button>
                            <button
                              type="button"
                              @click="cancelEditComment"
                              class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 transition-colors"
                            >
                              Cancel
                            </button>
                          </div>
                        </template>
                        <p v-else class="text-sm text-gray-700 whitespace-pre-line">
                          {{ comment.comment }}
                        </p>
                      </div>
                    </div>

                    <!-- Comment Input -->
                    <div class="flex gap-3">
                      <div
                        class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0"
                      >
                        {{
                          String(authStore.user?.name || "U")
                            .charAt(0)
                            .toUpperCase()
                        }}
                      </div>
                      <div class="flex-1">
                        <textarea
                          v-model="newComment"
                          class="w-full border border-gray-200 rounded-lg p-3 text-sm resize-none focus:border-[#0C51D9] focus:ring-2 focus:ring-[#0C51D9] focus:ring-opacity-20 transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                          :placeholder="
                            canCollaborateTask
                              ? 'Write a comment...'
                              : 'Comment is locked for this task status'
                          "
                          rows="3"
                          :disabled="!canCollaborateTask || isSubmittingComment"
                        ></textarea>
                        <div class="mt-2 flex justify-end">
                          <button
                            type="button"
                            @click="handleCreateComment"
                            :disabled="
                              !canCollaborateTask ||
                              !newComment.trim() ||
                              isSubmittingComment
                            "
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-[#0C51D9] text-white hover:bg-[#0a42b3] disabled:bg-gray-300 disabled:cursor-not-allowed"
                          >
                            {{ isSubmittingComment ? "Posting..." : "Post Comment" }}
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Attachments -->
                <div>
                  <div class="flex items-center gap-2 mb-3">
                    <Paperclip class="w-4 h-4 text-gray-500" />
                    <h3 class="text-sm font-semibold text-gray-700">
                      Attachments
                    </h3>
                  </div>
                  <input
                    ref="fileInputRef"
                    type="file"
                    class="hidden"
                    @change="handleAttachmentSelected"
                  />
                  <button
                    type="button"
                    @click="triggerAttachmentPicker"
                    :disabled="!canCollaborateTask || isUploadingAttachment"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium text-gray-700 transition-colors disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
                  >
                    {{ isUploadingAttachment ? "Uploading..." : "Add an attachment" }}
                  </button>

                  <div class="mt-3 space-y-2">
                    <div
                      v-if="attachmentsLoading"
                      class="text-sm text-gray-500 bg-gray-50 rounded-lg px-3 py-2"
                    >
                      Loading attachments...
                    </div>

                    <div
                      v-else-if="attachments.length === 0"
                      class="text-sm text-gray-500 bg-gray-50 rounded-lg px-3 py-2"
                    >
                      No attachments yet.
                    </div>

                    <div
                      v-for="attachment in attachments"
                      :key="attachment.id"
                      class="flex items-center justify-between gap-3 p-3 border border-gray-100 rounded-lg"
                    >
                      <div class="min-w-0">
                        <a
                          :href="attachment.file_url"
                          target="_blank"
                          rel="noreferrer"
                          class="text-sm font-medium text-[#0C51D9] hover:underline break-all"
                        >
                          {{ attachment.file_name }}
                        </a>
                        <p class="text-xs text-gray-500 mt-1">
                          {{ attachment.employee?.user?.name || "Unknown" }}
                          • {{ formatDateTime(attachment.created_at) }}
                        </p>
                      </div>
                      <button
                        v-if="canMutateEntityOwner(attachment.staff_member_id)"
                        type="button"
                        @click="handleDeleteAttachment(attachment)"
                        class="text-gray-400 hover:text-red-600 transition-colors"
                      >
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Sidebar (Right Side) -->
              <div class="space-y-6">
                <!-- Assignee -->
                <div class="relative">
                  <h3
                    class="text-xs font-semibold text-gray-500 uppercase mb-3"
                  >
                    Assignee
                  </h3>

                  <!-- Selected Assignee Display -->
                  <div
                    class="p-3 bg-gray-50 rounded-lg border border-gray-200 mb-2"
                    v-if="selectedAssignee"
                  >
                    <div class="flex items-center gap-3">
                      <img
                        :src="selectedAssignee?.user?.profile_photo"
                        :alt="selectedAssignee?.user?.name"
                        class="w-10 h-10 rounded-full object-cover"
                        v-if="selectedAssignee?.user?.profile_photo"
                      />
                      <div
                        class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-100"
                        v-else
                      >
                        <User class="w-4 h-4 text-gray-400" />
                      </div>
                      <div class="flex-1">
                        <h4 class="text-sm font-semibold text-gray-900">
                          {{ selectedAssignee?.user?.name }}
                        </h4>
                        <p class="text-xs text-gray-500">
                          {{ selectedAssignee?.job_information?.job_title }}
                        </p>
                      </div>
                      <button
                        v-if="canManageAssignee"
                        type="button"
                        @click="handleRemoveAssignee"
                        class="text-gray-400 hover:text-red-600 transition-colors"
                      >
                        <X class="w-4 h-4" />
                      </button>
                    </div>
                  </div>

                  <!-- Dropdown Toggle Button -->
                  <button
                    v-if="canManageAssignee"
                    type="button"
                    class="w-full border border-gray-200 rounded-lg hover:border-[#0C51D9] hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center gap-3 text-left"
                    @click="toggleAssigneeDropdown"
                  >
                    <UserCheck class="w-4 h-4 text-gray-400" />
                    <span class="text-sm font-medium text-gray-700 flex-1">
                      {{
                        selectedAssignee ? "Change assignee" : "Select assignee"
                      }}
                    </span>
                    <ChevronDown
                      class="w-4 h-4 text-gray-400 transition-transform duration-200"
                      :class="{ 'rotate-180': assigneeDropdown }"
                    />
                  </button>

                  <!-- Dropdown Menu -->
                  <div
                    v-if="assigneeDropdown && canManageAssignee"
                    class="absolute top-full left-0 right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg z-10 max-h-80 overflow-hidden flex flex-col"
                  >
                    <!-- Search -->
                    <div class="p-3 border-b border-gray-200">
                      <div class="relative flex items-center">
                        <div class="absolute left-3 pointer-events-none">
                          <Search class="w-4 h-4 text-gray-400" />
                        </div>
                        <input
                          type="text"
                          v-model="searchAssignee"
                          placeholder="Search employees..."
                          class="w-full pl-10 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-[#0C51D9] focus:outline-none"
                        />
                      </div>
                    </div>

                    <!-- Staff Member List -->
                    <div class="overflow-y-auto max-h-64">
                      <button
                        v-for="staffMember in staffMembers"
                        :key="staffMember.id"
                        type="button"
                        @click="handleSelectAssignee(employee)"
                        class="w-full p-3 hover:bg-gray-50 transition-colors flex items-center gap-3 text-left"
                      >
                        <img
                          :src="employee.user?.profile_photo"
                          :alt="employee.user?.name"
                          class="w-8 h-8 rounded-full object-cover"
                          v-if="employee.user?.profile_photo"
                        />
                        <div
                          class="w-8 h-8 rounded-full flex items-center justify-center bg-gray-100"
                          v-else
                        >
                          <User class="w-3 h-3 text-gray-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                          <p class="text-sm font-medium text-gray-900 truncate">
                            {{ employee.user?.name }}
                          </p>
                          <p class="text-xs text-gray-500 truncate">
                            {{ employee.job_information?.job_title }}
                          </p>
                        </div>
                      </button>

                      <!-- No Results -->
                      <div
                        v-if="staffMembers.length === 0"
                        class="p-4 text-center"
                      >
                        <SearchX class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                        <p class="text-sm text-gray-500">No employees found</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Due Date -->
                <div>
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase">
                      Due Date
                    </h3>
                    <button
                      v-if="!dueDateEditing && canEditDueDate"
                      type="button"
                      @click="toggleDueDateEdit"
                      class="text-gray-400 hover:text-[#0C51D9] transition-colors"
                    >
                      <Pencil class="w-3 h-3" />
                    </button>
                  </div>

                  <!-- Display Mode -->
                  <div v-if="!dueDateEditing" class="flex items-center gap-2">
                    <div class="p-2 bg-gray-100 rounded-lg">
                      <Calendar class="w-4 h-4 text-gray-600" />
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-900">
                        {{ formatDate(task.due_date) }}
                      </p>
                    </div>
                  </div>

                  <!-- Edit Mode -->
                  <div v-else class="space-y-2">
                    <input
                      type="date"
                      v-model="editedDueDate"
                      class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-[#0C51D9] focus:outline-none"
                    />
                    <div class="flex gap-2">
                      <button
                        type="button"
                        @click="handleUpdateDueDate"
                        class="flex-1 px-3 py-1.5 bg-[#0C51D9] text-white rounded-lg text-xs font-medium hover:bg-[#0a42b3] transition-colors flex items-center justify-center gap-1"
                      >
                        <Check class="w-3 h-3" />
                        Save
                      </button>
                      <button
                        type="button"
                        @click="cancelDueDateEdit"
                        class="flex-1 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 transition-colors"
                      >
                        Cancel
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Status -->
                <div>
                  <h3
                    class="text-xs font-semibold text-gray-500 uppercase mb-3"
                  >
                    Status
                  </h3>
                  <div
                    :class="getTaskStatusClass(task.status)"
                    class="px-3 py-2 rounded-lg text-sm font-medium"
                  >
                    {{ formatStatusLabel(task.status) }}
                  </div>
                  <div
                    v-if="task.status === 'review'"
                    class="mt-2 inline-flex px-2.5 py-1 rounded-md bg-amber-100 text-amber-700 text-xs font-semibold"
                  >
                    Need Review
                  </div>
                  <p
                    v-if="task.status === 'rejected' && task.rejected_reason"
                    class="mt-2 text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg px-3 py-2"
                  >
                    Reason: {{ task.rejected_reason }}
                  </p>
                </div>

                <!-- Status Timeline -->
                <div>
                  <h3
                    class="text-xs font-semibold text-gray-500 uppercase mb-3"
                  >
                    Status Timeline
                  </h3>
                  <div
                    v-if="statusLogsLoading"
                    class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2"
                  >
                    Loading status timeline...
                  </div>
                  <div
                    v-else-if="statusLogs.length === 0"
                    class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2"
                  >
                    No status changes recorded yet.
                  </div>
                  <div v-else class="space-y-2 max-h-52 overflow-y-auto pr-1">
                    <div
                      v-for="log in statusLogs"
                      :key="log.id"
                      class="border border-gray-100 rounded-lg px-3 py-2"
                    >
                      <p class="text-xs font-semibold text-gray-800">
                        {{ formatStatusLabel(log.from_status) }} ->
                        {{ formatStatusLabel(log.to_status) }}
                      </p>
                      <p class="text-[11px] text-gray-500 mt-0.5">
                        {{ log.changed_by_employee?.user?.name || "System" }}
                        • {{ formatDateTime(log.changed_at) }}
                      </p>
                      <p
                        v-if="log.reason"
                        class="text-[11px] text-red-700 mt-1 bg-red-50 border border-red-100 rounded px-2 py-1"
                      >
                        Reason: {{ log.reason }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Actions -->
                <div>
                  <h3
                    class="text-xs font-semibold text-gray-500 uppercase mb-3"
                  >
                    Actions
                  </h3>
                  <div class="space-y-2">
                    <button
                      v-if="canStartTask"
                      @click="handleUpdateStatus('in_progress')"
                      class="w-full px-4 py-2 bg-[#0C51D9] hover:bg-[#0a42b3] text-white rounded-lg text-sm font-medium transition-colors"
                    >
                      Start Task
                    </button>
                    <button
                      v-if="canSubmitForReview"
                      @click="handleUpdateStatus('review')"
                      class="w-full px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition-colors"
                    >
                      Task Selesai - Submit for Review
                    </button>
                    <button
                      v-if="canStartRework"
                      @click="handleUpdateStatus('in_progress')"
                      class="w-full px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors"
                    >
                      Start Rework
                    </button>
                    <button
                      v-if="canApproveReview"
                      @click="handleUpdateStatus('done')"
                      class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors"
                    >
                      Approve and Mark Done
                    </button>
                    <button
                      v-if="canRejectReview"
                      @click="handleRejectWithReason"
                      class="w-full px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-colors"
                    >
                      Reject Task
                    </button>
                    <button
                      v-if="canReopenDoneAsRejected"
                      @click="handleRejectWithReason"
                      class="w-full px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-colors"
                    >
                      Reopen as Rejected
                    </button>
                    <button
                      v-if="canDeleteTask"
                      @click="handleDelete"
                      class="w-full px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-sm font-medium transition-colors"
                    >
                      Delete Task
                    </button>
                    <p
                      v-if="!canStartTask && !canSubmitForReview && !canStartRework && !canApproveReview && !canRejectReview && !canReopenDoneAsRejected && !canDeleteTask"
                      class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2"
                    >
                      No task actions available for this role and status.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
  </ModalWrapper>
</template>
