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
import type { ComponentPropsWithoutRef } from 'react';
import Button01 from '../../elements/button-01';
import Input01 from '../../elements/input-01';

interface NewsletterForm01Props extends ComponentPropsWithoutRef<'div'> {
    /** Input placeholder text. */
    placeholder?: string;
    /** Submit button label. */
    buttonLabel?: string;
    /** Privacy note under the form. Hidden when empty. */
    note?: string;
}

export default function NewsletterForm01({
    placeholder = 'you@example.com',
    buttonLabel = 'Subscribe',
    note = '',
    className = '',
    ...rest
}: NewsletterForm01Props) {
    return (
        <div {...rest} className={`flex w-full max-w-md flex-col gap-3 ${className}`}>
            <div className="flex flex-col gap-3 sm:flex-row">
                <div className="flex-1">
                    <Input01 placeholder={placeholder} />
                </div>
                <Button01 label={buttonLabel} href="#" className="shrink-0" />
            </div>
            {note !== '' && <p className="text-xs leading-5 text-neutral-500 dark:text-neutral-400">{note}</p>}
        </div>
    );
}
