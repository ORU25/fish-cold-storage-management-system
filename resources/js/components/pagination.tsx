import { cn } from '@/lib/utils';
import { type Paginated } from '@/types';
import { Link } from '@inertiajs/react';

/** Laravel paginator links. Labels come from the server's own pagination translations. */
export function Pagination({ links }: { links: Paginated<unknown>['links'] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="mt-4 flex flex-wrap gap-1">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={cn('rounded-md border px-3 py-1 text-sm', link.active && 'bg-primary text-primary-foreground')}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span key={index} className="text-muted-foreground px-3 py-1 text-sm" dangerouslySetInnerHTML={{ __html: link.label }} />
                ),
            )}
        </div>
    );
}
