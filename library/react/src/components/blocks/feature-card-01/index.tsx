/**
 * @component  feature-card-01
 * @name       Feature Card 01
 * @level      block
 * @usage      feature-grid
 * @industries
 * @tags       minimal, icons
 * @access     free
 * @source     https://www.raycast.com
 * @deps       lucide
 * @version    1.0.0
 */
import { Blocks, ChartBar, Cloud, Gauge, Globe, Layers, Lock, Rocket, ShieldCheck, Sparkles, Users, Zap } from 'lucide-react';
import type { ComponentPropsWithoutRef, ComponentType } from 'react';

interface FeatureCard01Props extends ComponentPropsWithoutRef<'div'> {
    /** Lucide icon name: Rocket, Zap, ShieldCheck, ChartBar, Globe, Users, Gauge, Layers, Lock, Cloud, Blocks or Sparkles. */
    icon?: string;
    /** Feature title. */
    title?: string;
    /** Supporting description. Hidden when empty. */
    description?: string;
}

const ICONS: Record<string, ComponentType<{ className?: string }>> = {
    Blocks,
    ChartBar,
    Cloud,
    Gauge,
    Globe,
    Layers,
    Lock,
    Rocket,
    ShieldCheck,
    Sparkles,
    Users,
    Zap,
};

export default function FeatureCard01({
    icon = 'Rocket',
    title = 'Feature title',
    description = '',
    className = '',
    ...rest
}: FeatureCard01Props) {
    const Icon = ICONS[icon] ?? Sparkles;

    return (
        <div
            {...rest}
            className={`flex flex-col gap-4 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900 ${className}`}
        >
            <div className="flex size-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                <Icon className="size-5" />
            </div>
            <div className="flex flex-col gap-2">
                <h3 className="text-base font-semibold text-neutral-900 dark:text-white">{title}</h3>
                {description !== '' && <p className="text-sm leading-6 text-neutral-600 dark:text-neutral-400">{description}</p>}
            </div>
        </div>
    );
}
