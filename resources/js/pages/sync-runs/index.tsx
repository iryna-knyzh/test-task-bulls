import { Head, Link } from '@inertiajs/react';
import { edit } from '@/actions/App/Http/Controllers/SpreadsheetController';
import Pagination from '@/components/pagination';
import StatusTag from '@/components/status-tag';
import { formatDateTime } from '@/lib/format';
import type { Paginated, SyncRun } from '@/types';

export default function SyncRunsIndex({ runs }: { runs: Paginated<SyncRun> }) {
    return (
        <>
            <Head title="Sync log" />

            <h1 className="title">Sync log</h1>

            {runs.data.length === 0 ? (
                <div className="box has-text-grey">
                    No syncs yet. Use “Sync now” or wait for the daily schedule.
                </div>
            ) : (
                <div className="box table-scroll">
                    <table className="table is-fullwidth">
                        <thead>
                            <tr>
                                <th>Started</th>
                                <th>Spreadsheet</th>
                                <th>Status</th>
                                <th>Rows</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            {runs.data.map((run) => (
                                <tr key={run.id}>
                                    <td>{formatDateTime(run.started_at)}</td>
                                    <td>
                                        {run.spreadsheet && (
                                            <Link
                                                href={edit.url(
                                                    run.spreadsheet.id,
                                                )}
                                            >
                                                {run.spreadsheet.name}
                                            </Link>
                                        )}
                                    </td>
                                    <td>
                                        <StatusTag status={run.status} />
                                    </td>
                                    <td>{run.rows_processed}</td>
                                    <td className="is-size-7">
                                        {run.error && (
                                            <p className="has-text-danger">
                                                {run.error}
                                            </p>
                                        )}
                                        {run.warnings?.map((warning) => (
                                            <p key={warning}>{warning}</p>
                                        ))}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Pagination paginator={runs} />
        </>
    );
}
