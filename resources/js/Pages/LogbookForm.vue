<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  submitUrl: {
    type: String,
    default: '/api/logbooks',
  },
});

const holidayError = ref('');

const form = useForm({
  report_date: '',
  done_tasks: '',
  next_tasks: '',
  appendix: null,
});

const hasErrors = computed(() => Object.keys(form.errors).length > 0 || holidayError.value.length > 0);

const onAppendixChange = (event) => {
  const [file] = event.target.files || [];
  form.appendix = file ?? null;
};

const submit = () => {
  holidayError.value = '';

  form.post(props.submitUrl, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset('done_tasks', 'next_tasks', 'appendix');
    },
    onError: (errors) => {
      const message = errors?.message || '';
      if (message.toLowerCase().includes('cannot submit reports on holidays')) {
        holidayError.value = 'Cannot submit reports on holidays';
      }
    },
  });
};
</script>

<template>
  <section class="mx-auto w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <header class="mb-6 border-b border-slate-100 pb-4">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Daily Logbook</h1>
      <p class="mt-1 text-sm text-slate-500">Record completed work, next plan, and supporting appendix.</p>
    </header>

    <form class="space-y-5" @submit.prevent="submit">
      <div>
        <label class="mb-2 block text-sm font-medium text-slate-700" for="report_date">Report Date</label>
        <input
          id="report_date"
          v-model="form.report_date"
          type="date"
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none ring-0 transition focus:border-slate-900"
        />
        <p v-if="form.errors.report_date" class="mt-1 text-sm text-rose-600">{{ form.errors.report_date }}</p>
      </div>

      <div>
        <label class="mb-2 block text-sm font-medium text-slate-700" for="done_tasks">Done Tasks</label>
        <textarea
          id="done_tasks"
          v-model="form.done_tasks"
          rows="5"
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none transition focus:border-slate-900"
          placeholder="Describe what was completed today..."
        />
        <p v-if="form.errors.done_tasks" class="mt-1 text-sm text-rose-600">{{ form.errors.done_tasks }}</p>
      </div>

      <div>
        <label class="mb-2 block text-sm font-medium text-slate-700" for="next_tasks">Next Tasks</label>
        <textarea
          id="next_tasks"
          v-model="form.next_tasks"
          rows="5"
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none transition focus:border-slate-900"
          placeholder="Describe what will be done next..."
        />
        <p v-if="form.errors.next_tasks" class="mt-1 text-sm text-rose-600">{{ form.errors.next_tasks }}</p>
      </div>

      <div>
        <label class="mb-2 block text-sm font-medium text-slate-700" for="appendix">Appendix (Optional)</label>
        <input
          id="appendix"
          type="file"
          class="block w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-slate-700"
          @change="onAppendixChange"
        />
        <p v-if="form.errors.appendix" class="mt-1 text-sm text-rose-600">{{ form.errors.appendix }}</p>
      </div>

      <div v-if="holidayError" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
        {{ holidayError }}
      </div>

      <div v-if="hasErrors && form.errors.message" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ form.errors.message }}
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <span v-if="form.processing">Submitting...</span>
          <span v-else>Submit Logbook</span>
        </button>
      </div>
    </form>
  </section>
</template>
