<script setup lang="ts">
/**
 * @component  input-01
 * @name       Input 01
 * @level      element
 * @usage      newsletter
 * @industries
 * @tags       minimal, form
 * @access     free
 * @source     https://linear.app
 * @deps
 * @version    1.0.0
 */
interface Input01Props {
    /** Field name used when submitted inside a form. */
    name?: string;
    /** HTML input type. */
    type?: 'email' | 'text';
    /** Placeholder text shown when empty. */
    placeholder?: string;
    /** Accessible label announced by screen readers. */
    ariaLabel?: string;
}

const props = withDefaults(defineProps<Input01Props>(), {
    name: 'email',
    type: 'email',
    placeholder: 'you@example.com',
    ariaLabel: 'Email address',
});
</script>

<template>
    <input
        :name="props.name"
        :type="props.type"
        :placeholder="props.placeholder"
        :aria-label="props.ariaLabel"
        class="block w-full rounded-lg border-0 bg-white px-4 py-2.5 text-sm text-neutral-900 shadow-sm ring-1 ring-inset ring-neutral-300 outline-none placeholder:text-neutral-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-neutral-900 dark:text-white dark:ring-neutral-700 dark:placeholder:text-neutral-500 dark:focus:ring-indigo-500"
    />
</template>
