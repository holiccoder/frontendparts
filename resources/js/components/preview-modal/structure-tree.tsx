import { LevelBadge } from '@/components/catalog/badges';
import { cn } from '@/lib/utils';
import type { TreeNode } from '@/types/catalog';
import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';

export interface HighlightPin {
    slug: string;
    instance: number | null;
}

interface StructureTreeProps {
    tree: TreeNode;
    /** Master switch from payload.features.tree_interactions (SPEC §5.5). */
    interactions: boolean;
    pinned: HighlightPin | null;
    onHighlight: (slug: string, instance: number | null) => void;
    onClearHighlight: () => void;
    onPin: (pin: HighlightPin | null) => void;
}

/** Depth (root = 0) from which nodes start collapsed — default expanded ≤ 2. */
const DEFAULT_EXPANDED_DEPTH = 2;

function collectInitiallyCollapsed(node: TreeNode, key: string, depth: number, into: Set<string>): Set<string> {
    if (depth >= DEFAULT_EXPANDED_DEPTH && node.children.length > 0) {
        into.add(key);
    }

    node.children.forEach((child) => collectInitiallyCollapsed(child, `${key}/${child.slug}`, depth + 1, into));

    return into;
}

interface TreeRowProps extends Omit<StructureTreeProps, 'tree'> {
    node: TreeNode;
    nodeKey: string;
    depth: number;
    collapsed: Set<string>;
    onToggle: (key: string) => void;
}

function TreeRow({ node, nodeKey, depth, collapsed, onToggle, interactions, pinned, onHighlight, onClearHighlight, onPin }: TreeRowProps) {
    const hasChildren = node.children.length > 0;
    const isCollapsed = collapsed.has(nodeKey);

    return (
        <div>
            <div
                className="flex items-center gap-1 rounded-md py-1 pr-1 transition hover:bg-neutral-100"
                style={{ paddingLeft: `${depth * 14 + 4}px` }}
                onMouseEnter={() => interactions && onHighlight(node.slug, null)}
                onMouseLeave={() => interactions && onClearHighlight()}
            >
                {hasChildren ? (
                    <button
                        type="button"
                        onClick={() => onToggle(nodeKey)}
                        aria-expanded={!isCollapsed}
                        aria-label={`${isCollapsed ? 'Expand' : 'Collapse'} ${node.name}`}
                        className="shrink-0 rounded p-0.5 text-neutral-400 transition hover:text-neutral-700"
                    >
                        {isCollapsed ? <ChevronRight className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
                    </button>
                ) : (
                    <span className="w-[22px] shrink-0" aria-hidden="true" />
                )}

                <Link
                    href={`/components/${node.usage}/${node.basename}`}
                    className="truncate text-sm font-medium text-neutral-800 transition hover:text-neutral-950 hover:underline"
                >
                    {node.name}
                </Link>

                <LevelBadge level={node.level} className="ml-1 shrink-0" />

                {node.instances > 1 && <span className="shrink-0 text-xs font-medium text-neutral-400">×{node.instances}</span>}
            </div>

            {node.instances > 1 && (
                <div className="flex max-h-20 flex-wrap gap-1 overflow-y-auto pb-1" style={{ paddingLeft: `${depth * 14 + 30}px` }}>
                    {Array.from({ length: node.instances }, (_, index) => {
                        const instance = index + 1;
                        const isPinned = pinned?.slug === node.slug && pinned.instance === instance;

                        return (
                            <button
                                key={instance}
                                type="button"
                                disabled={!interactions}
                                onMouseEnter={() => interactions && onHighlight(node.slug, instance)}
                                onMouseLeave={() => interactions && onClearHighlight()}
                                onClick={() => interactions && onPin(isPinned ? null : { slug: node.slug, instance })}
                                aria-pressed={isPinned}
                                className={cn(
                                    'rounded border px-1.5 py-0.5 text-[10px] font-medium transition',
                                    isPinned
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-neutral-300 text-neutral-500 hover:border-indigo-300 hover:text-indigo-600',
                                    !interactions && 'cursor-default opacity-60',
                                )}
                            >
                                #{instance}
                            </button>
                        );
                    })}
                </div>
            )}

            {hasChildren && !isCollapsed && (
                <div>
                    {node.children.map((child) => (
                        <TreeRow
                            key={`${nodeKey}/${child.slug}`}
                            node={child}
                            nodeKey={`${nodeKey}/${child.slug}`}
                            depth={depth + 1}
                            collapsed={collapsed}
                            onToggle={onToggle}
                            interactions={interactions}
                            pinned={pinned}
                            onHighlight={onHighlight}
                            onClearHighlight={onClearHighlight}
                            onPin={onPin}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * Structure tree (SPEC §5.5): foldable nodes, level badges, ×n instance
 * chips, one-way highlight sync into the preview iframe (panel → iframe
 * only), child-page navigation on the node name, pin-on-click.
 */
export function StructureTree({ tree, interactions, pinned, onHighlight, onClearHighlight, onPin }: StructureTreeProps) {
    const [collapsed, setCollapsed] = useState<Set<string>>(() => collectInitiallyCollapsed(tree, tree.slug, 0, new Set()));

    const toggle = (key: string) => {
        setCollapsed((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    };

    if (tree.children.length === 0) {
        return <p className="py-6 text-center text-sm text-neutral-400">Self-contained element</p>;
    }

    return (
        <TreeRow
            node={tree}
            nodeKey={tree.slug}
            depth={0}
            collapsed={collapsed}
            onToggle={toggle}
            interactions={interactions}
            pinned={pinned}
            onHighlight={onHighlight}
            onClearHighlight={onClearHighlight}
            onPin={onPin}
        />
    );
}
