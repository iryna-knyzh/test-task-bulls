import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { index as spreadsheetsIndex } from '@/actions/App/Http/Controllers/SpreadsheetController';
import { index as syncRunsIndex } from '@/actions/App/Http/Controllers/SyncRunController';
import FlashNotification from '@/components/flash-notification';

export default function AppLayout({ children }: { children: ReactNode }) {
    const { name } = usePage().props;

    return (
        <>
            <nav className="navbar is-dark" aria-label="main navigation">
                <div className="container">
                    <div className="navbar-brand">
                        <span className="navbar-item has-text-weight-bold">
                            {name}
                        </span>
                        <Link
                            href={spreadsheetsIndex.url()}
                            className="navbar-item"
                        >
                            Spreadsheets
                        </Link>
                        <Link
                            href={syncRunsIndex.url()}
                            className="navbar-item"
                        >
                            Sync log
                        </Link>
                    </div>
                </div>
            </nav>

            <section className="section">
                <div className="container">
                    <FlashNotification />
                    {children}
                </div>
            </section>
        </>
    );
}
