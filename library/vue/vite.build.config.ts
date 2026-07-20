import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';
import { fpInstrument } from './fp-instrument';

/**
 * Preview artifact build (SPEC §5.2) — driven by the Laravel
 * BuildComponentPreview job, never by hand:
 *
 *   FP_ENTRY      absolute path of the generated `.build/*.entry.ts`
 *   FP_OUT_DIR    absolute temp output dir (PHP inlines the assets afterwards)
 *   FP_INSTRUMENT when "1", injects data-fp-* instrumentation (SPEC §2.3)
 *
 * Produces a single JS chunk (inlineDynamicImports) and a single CSS file,
 * which PHP inlines into one self-contained HTML document.
 */
export default defineConfig({
    plugins: [
        vue({
            template: {
                compilerOptions: {
                    nodeTransforms: process.env.FP_INSTRUMENT === '1' ? [fpInstrument()] : [],
                },
            },
        }),
        tailwindcss(),
    ],
    build: {
        outDir: process.env.FP_OUT_DIR ?? '.build/out',
        emptyOutDir: true,
        cssCodeSplit: false,
        rollupOptions: {
            input: process.env.FP_ENTRY,
            output: { inlineDynamicImports: true },
        },
    },
    logLevel: 'warn',
});
