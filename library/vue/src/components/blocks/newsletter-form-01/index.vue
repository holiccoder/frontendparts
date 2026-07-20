<script setup lang="ts">
/**
 * @component  newsletter-form-01
 * @name       Newsletter Form 01
 * @level      block
 * @usage      newsletter
 * @industries
 * @tags       minimal, form
 * @access     free
 * @source     https://buttondown.com
 * @deps
 * @version    1.0.0
 */
import Button01 from '../../elements/button-01/index.vue';
import Input01 from '../../elements/input-01/index.vue';

interface NewsletterForm01Props {
    /** Input placeholder text. */
    placeholder?: string;
    /** Submit button label. */
    buttonLabel?: string;
    /** Privacy note under the form. Hidden when empty. */
    note?: string;
}

const props = withDefaults(defineProps<NewsletterForm01Props>(), {
    placeholder: 'you@example.com',
    buttonLabel: 'Subscribe',
    note: '',
});
</script>

<template>
    <div class="flex w-full max-w-md flex-col gap-3">
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <Input01 :placeholder="props.placeholder" />
            </div>
            <Button01 :label="props.buttonLabel" href="#" class="shrink-0" />
        </div>
        <p v-if="props.note !== ''" class="text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ props.note }}</p>
    </div>
</template>
