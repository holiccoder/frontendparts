/**
 * @component  faq-item-01
 * @name       FAQ Item 01
 * @level      block
 * @usage      faq
 * @industries
 * @tags       interactive, accordion, a11y
 * @access     pro
 * @source     https://www.notion.so
 * @deps
 * @version    1.0.0
 */
import { useId, useState } from 'react';
import type { ComponentPropsWithoutRef } from 'react';

interface FaqItem01Props extends ComponentPropsWithoutRef<'div'> {
    /** Question shown on the accordion trigger. */
    question?: string;
    /** Answer revealed when open. */
    answer?: string;
    /** Render open on first paint. */
    defaultOpen?: boolean;
}

export default function FaqItem01({
    question = 'How does this work?',
    answer = '',
    defaultOpen = false,
    className = '',
    ...rest
}: FaqItem01Props) {
    const [open, setOpen] = useState(defaultOpen);
    const id = useId();
    const buttonId = `${id}-trigger`;
    const panelId = `${id}-panel`;

    return (
        <div {...rest} className={`border-b border-neutral-200 dark:border-neutral-800 ${className}`}>
            <button
                type="button"
                id={buttonId}
                aria-expanded={open}
                aria-controls={panelId}
                onClick={() => setOpen((value) => !value)}
                className="flex w-full items-center justify-between gap-4 py-5 text-left text-base font-medium text-neutral-900 transition-colors hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-white dark:hover:text-indigo-400 dark:focus-visible:outline-indigo-400"
            >
                {question}
                <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    aria-hidden="true"
                    className={`size-5 shrink-0 text-neutral-400 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
                >
                    <path d="M5 7.5l5 5 5-5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>
            <div
                className={`grid transition-all duration-200 ease-out ${open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'}`}
            >
                <div className="overflow-hidden">
                    <div
                        id={panelId}
                        role="region"
                        aria-labelledby={buttonId}
                        aria-hidden={!open}
                        className="pb-5 text-sm leading-6 text-neutral-600 dark:text-neutral-400"
                    >
                        {answer}
                    </div>
                </div>
            </div>
        </div>
    );
}
