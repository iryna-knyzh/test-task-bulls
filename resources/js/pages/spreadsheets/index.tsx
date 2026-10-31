import { Head, Link } from '@inertiajs/react';
import {
    create,
    edit,
} from '@/actions/App/Http/Controllers/SpreadsheetController';
import { store as sync } from '@/actions/App/Http/Controllers/SyncController';
import ApiKeyHint from '@/components/api-key-hint';
import StatusTag from '@/components/status-tag';
import { formatDateTime } from '@/lib/format';
import type { Spreadsheet } from '@/types';

type Props = {
    spreadsheets: Spreadsheet[];
    hasApiKey: boolean;
};

export default function SpreadsheetsIndex({ spreadsheets, hasApiKey }: Props) {
    return (
        <>
            <Head title="Spreadsheets" />

            <div className="level">
                <div className="level-left">
                    <h1 className="title">Spreadsheets</h1>
                </div>
                <div className="level-right">
                    <Link href={create.url()} className="button is-primary">
                        Add spreadsheet
                    </Link>
                </div>
            </div>

            <ApiKeyHint hasApiKey={hasApiKey} />

            {spreadsheets.length === 0 ? (
                <div className="box has-text-centered has-text-grey">
                    No spreadsheets yet. Add one to start importing data.
                </div>
            ) : (
                <div className="box table-scroll">
                    <table className="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Tabs</th>
                                <th>Daily sync</th>
                                <th>Last run</th>
                                <th>Last success</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {spreadsheets.map((spreadsheet) => (
                                <tr key={spreadsheet.id}>
                                    <td>
                                        <Link
                                            href={edit.url(spreadsheet.id)}
                                            className="has-text-weight-semibold"
                                        >
                                            {spreadsheet.name}
                                        </Link>
                                        <p className="is-size-7 has-text-grey is-monospace">
                                            {spreadsheet.google_spreadsheet_id}
                                        </p>
                                    </td>
                                    <td>{spreadsheet.tabs_count}</td>
                                    <td>
                                        {spreadsheet.is_active ? (
                                            <span className="tag is-success is-light">
                                                on
                                            </span>
                                        ) : (
                                            <span className="tag is-light">
                                                off
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        <StatusTag
                                            status={
                                                spreadsheet.latest_sync_run
                                                    ?.status
                                            }
                                        />
                                    </td>
                                    <td>
                                        {formatDateTime(
                                            spreadsheet.last_synced_at,
                                        )}
                                    </td>
                                    <td className="has-text-right">
                                        <div className="buttons is-right">
                                            <Link
                                                href={sync(spreadsheet.id)}
                                                as="button"
                                                className="button is-small is-info is-light"
                                                preserveScroll
                                            >
                                                Sync now
                                            </Link>
                                            <Link
                                                href={edit.url(spreadsheet.id)}
                                                className="button is-small"
                                            >
                                                Configure
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}
