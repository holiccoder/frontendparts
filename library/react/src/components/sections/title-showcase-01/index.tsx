/**
 * @component  title-showcase-01
 * @name       Title Showcase 01
 * @level      section
 * @usage      feature-grid
 * @industries
 * @tags       minimal, typography
 * @access     free
 * @source     https://tailwindcss.com
 * @deps
 * @version    1.0.0
 */
import SectionTitle01 from '../../elements/section-title-01';

interface SectionTitleSlice {
    eyebrow?: string;
    heading?: string;
    description?: string;
    align?: 'left' | 'center';
}

interface TitleShowcase01Props {
    /** Child slices keyed by child slug (library README `children` convention). */
    children?: {
        'section-title-01'?: SectionTitleSlice[];
    };
}

export default function TitleShowcase01({ children }: TitleShowcase01Props) {
    const [first = {}, second = {}] = children?.['section-title-01'] ?? [];

    return (
        <div className="flex flex-col gap-8 px-6 py-16 sm:gap-12">
            <SectionTitle01 {...first} />
            <SectionTitle01 {...second} />
        </div>
    );
}
