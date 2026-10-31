import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { edit as editSpreadsheet } from '@/actions/App/Http/Controllers/SpreadsheetController';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/TabMappingController';
import FieldError from '@/components/field-error';
import type { ColumnMapping, ColumnType, SheetTab, TabMapping } from '@/types';

type Props = {
    tab: SheetTab;
    mapping: TabMapping | null;
    columnTypes: ColumnType[];
};

type ColumnRow = Omit<ColumnMapping, 'id'>;

const emptyRow: ColumnRow = {
    source_column: '',
    target_column: '',
    data_type: 'string',
};

export default function MappingForm({ tab, mapping, columnTypes }: Props) {
    const form = useForm({
        target_table: mapping?.target_table ?? '',
        columns: mapping?.columns?.map(
            ({ source_column, target_column, data_type }) => ({
                source_column,
                target_column,
                data_type,
            }),
        ) ?? [emptyRow],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const backUrl = editSpreadsheet.url(tab.spreadsheet_id);

    function updateRow(rowIndex: number, changes: Partial<ColumnRow>) {
        form.setData(
            'columns',
            form.data.columns.map((row, index) =>
                index === rowIndex ? { ...row, ...changes } : row,
            ),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        if (mapping) {
            form.put(update.url(mapping.id));
        } else {
            form.post(store.url(tab.id));
        }
    }

    return (
        <>
            <Head title="Mapping" />

            <h1 className="title">
                {tab.tab_name} →{' '}
                {mapping ? mapping.target_table : 'new mapping'}
            </h1>
            <p className="subtitle is-6 has-text-grey">
                Sheet columns are matched by header name. Missing tables and
                columns are created on the next sync.
            </p>

            <form onSubmit={submit} className="box">
                <div className="field">
                    <label className="label" htmlFor="target_table">
                        Database table
                    </label>
                    <input
                        id="target_table"
                        className="input is-monospace"
                        value={form.data.target_table}
                        onChange={(event) =>
                            form.setData('target_table', event.target.value)
                        }
                        placeholder="sales"
                        required
                    />
                    <FieldError message={errors.target_table} />
                </div>

                <table className="table is-fullwidth">
                    <thead>
                        <tr>
                            <th>Sheet header</th>
                            <th>Table column</th>
                            <th>Type</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {form.data.columns.map((row, rowIndex) => (
                            <tr key={rowIndex}>
                                <td>
                                    <input
                                        className="input is-small"
                                        value={row.source_column}
                                        onChange={(event) =>
                                            updateRow(rowIndex, {
                                                source_column:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="product_name"
                                    />
                                    <FieldError
                                        message={
                                            errors[
                                                `columns.${rowIndex}.source_column`
                                            ]
                                        }
                                    />
                                </td>
                                <td>
                                    <input
                                        className="input is-small is-monospace"
                                        value={row.target_column}
                                        onChange={(event) =>
                                            updateRow(rowIndex, {
                                                target_column:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="name"
                                    />
                                    <FieldError
                                        message={
                                            errors[
                                                `columns.${rowIndex}.target_column`
                                            ]
                                        }
                                    />
                                </td>
                                <td>
                                    <div className="select is-small">
                                        <select
                                            value={row.data_type}
                                            onChange={(event) =>
                                                updateRow(rowIndex, {
                                                    data_type: event.target
                                                        .value as ColumnType,
                                                })
                                            }
                                        >
                                            {columnTypes.map((type) => (
                                                <option key={type}>
                                                    {type}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        className="delete"
                                        aria-label="Remove column"
                                        onClick={() =>
                                            form.setData(
                                                'columns',
                                                form.data.columns.filter(
                                                    (_, index) =>
                                                        index !== rowIndex,
                                                ),
                                            )
                                        }
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <FieldError message={errors.columns} />

                <div className="buttons">
                    <button
                        type="button"
                        className="button is-small"
                        onClick={() =>
                            form.setData('columns', [
                                ...form.data.columns,
                                emptyRow,
                            ])
                        }
                    >
                        + Add column
                    </button>
                </div>

                <div className="buttons">
                    <button
                        type="submit"
                        className={`button is-primary ${form.processing ? 'is-loading' : ''}`}
                    >
                        Save mapping
                    </button>
                    <Link href={backUrl} className="button is-text">
                        Cancel
                    </Link>
                </div>
            </form>
        </>
    );
}
