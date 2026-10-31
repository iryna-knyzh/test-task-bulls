import { Form, Head, Link } from '@inertiajs/react';
import {
    destroy as destroySpreadsheet,
    index,
} from '@/actions/App/Http/Controllers/SpreadsheetController';
import {
    destroy as destroyTab,
    store as storeTab,
    update as updateTab,
} from '@/actions/App/Http/Controllers/SheetTabController';
import { store as sync } from '@/actions/App/Http/Controllers/SyncController';
import {
    create as createMapping,
    destroy as destroyMapping,
    edit as editMapping,
} from '@/actions/App/Http/Controllers/TabMappingController';
import { show as showTable } from '@/actions/App/Http/Controllers/TargetTableController';
import FieldError from '@/components/field-error';
import ApiKeyHint from '@/components/api-key-hint';
import SpreadsheetForm from '@/components/spreadsheet-form';
import StatusTag from '@/components/status-tag';
import { formatDateTime } from '@/lib/format';
import type { SheetTab, Spreadsheet } from '@/types';

type Props = {
    spreadsheet: Spreadsheet;
    hasApiKey: boolean;
};

const confirmAction = (message: string) => () => window.confirm(message);

export default function SpreadsheetsEdit({ spreadsheet, hasApiKey }: Props) {
    const latestRun = spreadsheet.latest_sync_run;

    return (
        <>
            <Head title={spreadsheet.name} />

            <nav className="breadcrumb" aria-label="breadcrumbs">
                <ul>
                    <li>
                        <Link href={index.url()}>Spreadsheets</Link>
                    </li>
                    <li className="is-active">
                        <a aria-current="page">{spreadsheet.name}</a>
                    </li>
                </ul>
            </nav>

            <div className="level">
                <div className="level-left">
                    <div>
                        <h1 className="title">{spreadsheet.name}</h1>
                        <p className="subtitle is-6">
                            Last sync: <StatusTag status={latestRun?.status} />{' '}
                            {latestRun && formatDateTime(latestRun.started_at)}
                        </p>
                    </div>
                </div>
                <div className="level-right">
                    <Link
                        href={sync(spreadsheet.id)}
                        as="button"
                        className="button is-info"
                    >
                        Sync now
                    </Link>
                </div>
            </div>

            <ApiKeyHint hasApiKey={hasApiKey} />

            <div className="box">
                <SpreadsheetForm spreadsheet={spreadsheet} />
                <hr />
                <Link
                    href={destroySpreadsheet(spreadsheet.id)}
                    as="button"
                    className="button is-danger is-outlined is-small"
                    onBefore={confirmAction(
                        'Remove this spreadsheet? Imported data is kept.',
                    )}
                >
                    Remove spreadsheet
                </Link>
            </div>

            <h2 className="title is-4 mt-5">Tabs</h2>

            {spreadsheet.tabs?.map((tab) => (
                <TabCard key={tab.id} tab={tab} />
            ))}

            <div className="box">
                <h3 className="title is-6">Add tab</h3>
                <Form
                    {...storeTab.form(spreadsheet.id)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                >
                    {({ errors, processing }) => (
                        <TabFields
                            errors={errors}
                            processing={processing}
                            submitLabel="Add tab"
                        />
                    )}
                </Form>
            </div>
        </>
    );
}

function TabCard({ tab }: { tab: SheetTab }) {
    return (
        <div className="box">
            <Form
                {...updateTab.form(tab.id)}
                options={{ preserveScroll: true }}
            >
                {({ errors, processing }) => (
                    <TabFields
                        tab={tab}
                        errors={errors}
                        processing={processing}
                        submitLabel="Save tab"
                    />
                )}
            </Form>

            <table className="table is-fullwidth is-narrow mt-4">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Columns (sheet → table)</th>
                        <th className="has-text-right">
                            <Link
                                href={createMapping.url(tab.id)}
                                className="button is-small is-primary is-light"
                            >
                                Add mapping
                            </Link>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {tab.mappings?.map((mapping) => (
                        <tr key={mapping.id}>
                            <td>
                                <Link
                                    href={showTable.url(mapping.target_table)}
                                    className="is-monospace"
                                >
                                    {mapping.target_table}
                                </Link>
                            </td>
                            <td>
                                {mapping.columns
                                    ?.map(
                                        (column) =>
                                            `${column.source_column} → ${column.target_column}`,
                                    )
                                    .join(', ')}
                            </td>
                            <td>
                                <div className="buttons is-right are-small">
                                    <Link
                                        href={editMapping.url(mapping.id)}
                                        className="button"
                                    >
                                        Edit
                                    </Link>
                                    <Link
                                        href={destroyMapping(mapping.id)}
                                        as="button"
                                        className="button is-danger is-light"
                                        preserveScroll
                                        onBefore={confirmAction(
                                            `Remove mapping to "${mapping.target_table}"? Imported data is kept.`,
                                        )}
                                    >
                                        Remove
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <Link
                href={destroyTab(tab.id)}
                as="button"
                className="button is-danger is-light is-small"
                preserveScroll
                onBefore={confirmAction(
                    `Remove tab "${tab.tab_name}" and its mappings? Imported data is kept.`,
                )}
            >
                Remove tab
            </Link>
        </div>
    );
}

type TabFieldsProps = {
    tab?: SheetTab;
    errors: Record<string, string>;
    processing: boolean;
    submitLabel: string;
};

function TabFields({ tab, errors, processing, submitLabel }: TabFieldsProps) {
    return (
        <div className="columns is-vcentered">
            <div className="column is-6">
                <label className="label is-small">Tab name</label>
                <input
                    name="tab_name"
                    className="input"
                    defaultValue={tab?.tab_name}
                    placeholder="Sheet1"
                    required
                />
                <FieldError message={errors.tab_name} />
            </div>
            <div className="column is-3">
                <label className="label is-small">Header row</label>
                <input
                    name="header_row"
                    type="number"
                    min={1}
                    className="input"
                    defaultValue={tab?.header_row ?? 1}
                    required
                />
                <p className="help">
                    Row with column names. Data starts on the next row.
                </p>
                <FieldError message={errors.header_row} />
            </div>
            <div className="column is-3">
                <button
                    type="submit"
                    className={`button is-link mt-5 ${processing ? 'is-loading' : ''}`}
                >
                    {submitLabel}
                </button>
            </div>
        </div>
    );
}
