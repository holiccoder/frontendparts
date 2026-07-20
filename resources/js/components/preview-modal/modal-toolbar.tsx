import { cn } from '@/lib/utils';
import type { Framework } from '@/types/catalog';
import { Moon, PanelLeft, Sun } from 'lucide-react';

export const WIDTH_PRESETS = [
    { label: '375', value: 375, hotkey: '1' },
    { label: '768', value: 768, hotkey: '2' },
    { label: '1280', value: 1280, hotkey: '3' },
    { label: 'Full', value: 0, hotkey: '4' },
] as const;

interface ModalToolbarProps {
    width: number;
    measuredWidth: number | null;
    framework: Framework;
    darkMode: boolean;
    darkToggleEnabled: boolean;
    structureVisible: boolean;
    onWidthChange: (width: number) => void;
    onFrameworkChange: (framework: Framework) => void;
    onToggleDark: () => void;
    onToggleStructure: () => void;
}

/**
 * Fixed modal toolbar (SPEC §5.4): viewport presets + live width readout,
 * React|Vue toggle (synced across preview / code / download), dark/light
 * toggle behind the features.preview_dark_toggle flag, structure panel
 * toggle.
 */
export function ModalToolbar({
    width,
    measuredWidth,
    framework,
    darkMode,
    darkToggleEnabled,
    structureVisible,
    onWidthChange,
    onFrameworkChange,
    onToggleDark,
    onToggleStructure,
}: ModalToolbarProps) {
    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-neutral-200 px-6 py-2.5">
            <div className="flex items-center gap-1.5">
                <span className="text-[11px] font-semibold tracking-wide text-neutral-400 uppercase">Viewport</span>
                {WIDTH_PRESETS.map((preset) => (
                    <button
                        key={preset.label}
                        type="button"
                        onClick={() => onWidthChange(preset.value)}
                        title={`${preset.label === 'Full' ? 'Full width' : `${preset.label}px`} (${preset.hotkey})`}
                        className={cn(
                            'rounded-full border px-2.5 py-1 text-xs font-semibold transition',
                            width === preset.value
                                ? 'border-neutral-900 bg-neutral-900 text-white'
                                : 'border-neutral-300 text-neutral-600 hover:border-neutral-400',
                        )}
                    >
                        {preset.label}
                    </button>
                ))}
                <span className="ml-1 rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-[11px] text-neutral-500" aria-live="polite">
                    {measuredWidth !== null ? `${measuredWidth}px` : width === 0 ? '100%' : `${width}px`}
                </span>
            </div>

            <div className="inline-flex rounded-lg border border-neutral-200 bg-neutral-50 p-0.5" role="group" aria-label="Framework">
                {(['react', 'vue'] as const).map((option) => (
                    <button
                        key={option}
                        type="button"
                        onClick={() => onFrameworkChange(option)}
                        aria-pressed={framework === option}
                        title={option === 'react' ? 'React (r)' : 'Vue (v)'}
                        className={cn(
                            'rounded-md px-3.5 py-1 text-xs font-semibold capitalize transition',
                            framework === option ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-500 hover:text-neutral-900',
                        )}
                    >
                        {option}
                    </button>
                ))}
            </div>

            <div className="ml-auto flex items-center gap-1.5">
                {darkToggleEnabled && (
                    <button
                        type="button"
                        onClick={onToggleDark}
                        aria-pressed={darkMode}
                        title={darkMode ? 'Switch preview to light' : 'Switch preview to dark'}
                        className="inline-flex items-center justify-center rounded-md border border-neutral-300 bg-white p-1.5 text-neutral-500 transition hover:border-neutral-400 hover:text-neutral-900"
                    >
                        {darkMode ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                        <span className="sr-only">{darkMode ? 'Light mode' : 'Dark mode'}</span>
                    </button>
                )}
                <button
                    type="button"
                    onClick={onToggleStructure}
                    aria-pressed={structureVisible}
                    title="Toggle structure panel"
                    className={cn(
                        'inline-flex items-center justify-center rounded-md border p-1.5 transition',
                        structureVisible
                            ? 'border-neutral-900 bg-neutral-900 text-white'
                            : 'border-neutral-300 bg-white text-neutral-500 hover:border-neutral-400 hover:text-neutral-900',
                    )}
                >
                    <PanelLeft className="h-4 w-4" />
                    <span className="sr-only">Toggle structure panel</span>
                </button>
            </div>
        </div>
    );
}
