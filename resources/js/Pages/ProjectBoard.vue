<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
  apiBaseUrl: {
    type: String,
    default: '/api',
  },
  initialProjects: {
    type: Array,
    default: () => [],
  },
});

const loading = ref(false);
const globalError = ref('');
const projects = ref([...props.initialProjects]);

const commentBodyByProject = reactive({});
const commentSubmitting = reactive({});
const activityByProject = reactive({});
const activityLoading = reactive({});

const columns = [
  { key: 'todo', title: 'To Do', chip: 'bg-slate-100 text-slate-700' },
  { key: 'doing', title: 'Doing', chip: 'bg-amber-100 text-amber-800' },
  { key: 'done', title: 'Done', chip: 'bg-emerald-100 text-emerald-800' },
];

const groupedProjects = computed(() => {
  return columns.reduce((acc, col) => {
    acc[col.key] = projects.value.filter((project) => project.status === col.key);
    return acc;
  }, {});
});

const nextStatusMap = {
  todo: 'doing',
  doing: 'done',
  done: null,
};

const fetchProjects = async () => {
  loading.value = true;
  globalError.value = '';

  try {
    const { data } = await axios.get(`${props.apiBaseUrl}/projects`);
    projects.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
  } catch (error) {
    globalError.value = error?.response?.data?.message || 'Unable to fetch projects';
  } finally {
    loading.value = false;
  }
};

const moveToNextStatus = async (project) => {
  const next = nextStatusMap[project.status];
  if (!next) return;

  const previous = project.status;
  project.status = next;

  try {
    await axios.patch(`${props.apiBaseUrl}/projects/${project.id}/status`, { status: next });
    await fetchActivity(project.id);
  } catch (error) {
    project.status = previous;
    globalError.value = error?.response?.data?.message || 'Failed to update project status';
  }
};

const submitComment = async (projectId) => {
  const body = (commentBodyByProject[projectId] || '').trim();
  if (!body) return;

  commentSubmitting[projectId] = true;
  globalError.value = '';

  try {
    const { data } = await axios.post(`${props.apiBaseUrl}/projects/${projectId}/comments`, { body });
    const target = projects.value.find((project) => project.id === projectId);

    if (target) {
      target.comments = target.comments || [];
      target.comments.unshift(data?.data);
    }

    commentBodyByProject[projectId] = '';
  } catch (error) {
    globalError.value = error?.response?.data?.message || 'Failed to add comment';
  } finally {
    commentSubmitting[projectId] = false;
  }
};

const fetchActivity = async (projectId) => {
  activityLoading[projectId] = true;

  try {
    const { data } = await axios.get(`${props.apiBaseUrl}/projects/${projectId}/activity`);
    activityByProject[projectId] = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
  } catch {
    activityByProject[projectId] = activityByProject[projectId] || [];
  } finally {
    activityLoading[projectId] = false;
  }
};

onMounted(async () => {
  if (projects.value.length === 0) {
    await fetchProjects();
  }

  projects.value.forEach((project) => {
    if (!Array.isArray(activityByProject[project.id])) {
      activityByProject[project.id] = project.activity_logs || [];
    }
  });
});
</script>

<template>
  <section class="space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Project Board</h1>
      <p class="mt-1 text-sm text-slate-500">Track project flow, update status, and collaborate with comments.</p>
    </header>

    <div v-if="globalError" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      {{ globalError }}
    </div>

    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
      Loading projects...
    </div>

    <div v-else class="grid gap-4 lg:grid-cols-3">
      <article v-for="column in columns" :key="column.key" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ column.title }}</h2>
          <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="column.chip">
            {{ groupedProjects[column.key]?.length || 0 }}
          </span>
        </div>

        <div class="space-y-3">
          <div
            v-for="project in groupedProjects[column.key]"
            :key="project.id"
            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
          >
            <div class="mb-3 flex items-start justify-between gap-3">
              <div>
                <h3 class="text-sm font-semibold text-slate-900">{{ project.title }}</h3>
                <p class="mt-1 line-clamp-3 text-xs leading-5 text-slate-600">{{ project.description || 'No description' }}</p>
              </div>
              <button
                v-if="nextStatusMap[project.status]"
                type="button"
                class="shrink-0 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                @click="moveToNextStatus(project)"
              >
                Move to {{ nextStatusMap[project.status] }}
              </button>
            </div>

            <div class="mb-3 rounded-lg border border-slate-100 bg-slate-50 p-2.5">
              <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Activity</p>
              <button
                type="button"
                class="mb-2 text-xs font-medium text-slate-700 underline underline-offset-4"
                @click="fetchActivity(project.id)"
              >
                Refresh activity
              </button>

              <p v-if="activityLoading[project.id]" class="text-xs text-slate-500">Loading activity...</p>
              <ul v-else-if="(activityByProject[project.id] || []).length" class="space-y-1.5 text-xs text-slate-600">
                <li v-for="item in activityByProject[project.id]" :key="item.id" class="rounded-md bg-white px-2 py-1.5">
                  <span class="font-medium text-slate-700">{{ item.description || 'Status updated' }}</span>
                  <span class="ml-1 text-slate-500">{{ item.created_at }}</span>
                </li>
              </ul>
              <p v-else class="text-xs text-slate-500">No activity yet.</p>
            </div>

            <div class="space-y-2">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Add Comment</p>
              <textarea
                v-model="commentBodyByProject[project.id]"
                rows="2"
                class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs text-slate-900 outline-none transition focus:border-slate-900"
                placeholder="Write a short update..."
              />
              <div class="flex justify-end">
                <button
                  type="button"
                  :disabled="commentSubmitting[project.id]"
                  class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                  @click="submitComment(project.id)"
                >
                  {{ commentSubmitting[project.id] ? 'Saving...' : 'Post Comment' }}
                </button>
              </div>
            </div>
          </div>

          <p v-if="!(groupedProjects[column.key] || []).length" class="rounded-xl border border-dashed border-slate-300 px-3 py-6 text-center text-xs text-slate-500">
            No projects in this column.
          </p>
        </div>
      </article>
    </div>
  </section>
</template>
