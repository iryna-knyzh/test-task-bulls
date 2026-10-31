import { Head, Link } from '@inertiajs/react';
import { index } from '@/actions/App/Http/Controllers/SpreadsheetController';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/types';

type Props = {
    table: string;
    columns: string[];
    rows: Paginated<Record<string, string | number | boolean | null>>;
};

export default function TableShow({ table, columns, rows }: Props) {
    return (
        <>
            <Head title={`Table ${table}`} />

            <nav className="breadcrumb" aria-label="breadcrumbs">
                <ul>
                    <li>
                        <Link href={index.url()}>Spreadsheets</Link>
                    </li>
                    <li className="is-active">
                        <a aria-current="page">Table {table}</a>
                    </li>
                </ul>
            </nav>

            <h1 className="title is-monospace">{table}</h1>
            <p className="subtitle is-6 has-text-grey">
                {rows.total} row(s). Columns starting with “_” are filled by the
                sync.
            </p>

            <div className="box table-scroll">
                <table className="table is-fullwidth is-striped is-narrow is-size-7">
                    <thead>
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column}
                                    className={
                                        column.startsWith('_')
                                            ? 'has-text-grey'
                                            : ''
                                    }
                                >
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.data.map((row, rowIndex) => (
                            <tr key={String(row.id ?? rowIndex)}>
                                {columns.map((column) => (
                                    <td key={column}>
                                        {row[column] === null
                                            ? '—'
                                            : String(row[column])}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Pagination paginator={rows} />
        </>
    );
}
