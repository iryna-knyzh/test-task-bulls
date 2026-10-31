import { Link } from '@inertiajs/react';
import type { Paginated } from '@/types';

export default function Pagination<T>({
    paginator,
}: {
    paginator: Paginated<T>;
}) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <nav className="level mt-4" aria-label="pagination">
            <div className="level-left">
                Page {paginator.current_page} of {paginator.last_page}
            </div>
            <div className="level-right buttons">
                {paginator.prev_page_url && (
                    <Link
                        href={paginator.prev_page_url}
                        className="button is-small"
                    >
                        Previous
                    </Link>
                )}
                {paginator.next_page_url && (
                    <Link
                        href={paginator.next_page_url}
                        className="button is-small"
                    >
                        Next
                    </Link>
                )}
            </div>
        </nav>
    );
}
