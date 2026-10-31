import { Head } from '@inertiajs/react';
import SpreadsheetForm from '@/components/spreadsheet-form';

export default function SpreadsheetsCreate() {
    return (
        <>
            <Head title="Add spreadsheet" />

            <h1 className="title">Add spreadsheet</h1>

            <div className="columns">
                <div className="column is-6">
                    <div className="box">
                        <SpreadsheetForm />
                    </div>
                </div>
            </div>
        </>
    );
}
