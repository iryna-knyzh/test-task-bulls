import { Form, Link } from '@inertiajs/react';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/SpreadsheetController';
import FieldError from '@/components/field-error';
import type { Spreadsheet } from '@/types';

export default function SpreadsheetForm({
    spreadsheet,
}: {
    spreadsheet?: Spreadsheet;
}) {
    const action = spreadsheet ? update.form(spreadsheet.id) : store.form();

    return (
        <Form {...action} options={{ preserveScroll: true }}>
            {({ errors, processing }) => (
                <>
                    <div className="field">
                        <label className="label" htmlFor="name">
                            Name
                        </label>
                        <div className="control">
                            <input
                                id="name"
                                name="name"
                                className={`input ${errors.name ? 'is-danger' : ''}`}
                                defaultValue={spreadsheet?.name}
                                placeholder="Sales 2026"
                                required
                            />
                        </div>
                        <FieldError message={errors.name} />
                    </div>

                    <div className="field">
                        <label
                            className="label"
                            htmlFor="google_spreadsheet_id"
                        >
                            Google Sheets URL or ID
                        </label>
                        <div className="control">
                            <input
                                id="google_spreadsheet_id"
                                name="google_spreadsheet_id"
                                className={`input is-monospace ${errors.google_spreadsheet_id ? 'is-danger' : ''}`}
                                defaultValue={
                                    spreadsheet?.google_spreadsheet_id
                                }
                                placeholder="https://docs.google.com/spreadsheets/d/…/edit"
                                required
                            />
                        </div>
                        <FieldError message={errors.google_spreadsheet_id} />
                    </div>

                    <div className="field">
                        <label className="checkbox">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                defaultChecked={spreadsheet?.is_active ?? true}
                            />{' '}
                            Include in the daily sync
                        </label>
                    </div>

                    <div className="buttons">
                        <button
                            type="submit"
                            className={`button is-primary ${processing ? 'is-loading' : ''}`}
                            disabled={processing}
                        >
                            {spreadsheet ? 'Save' : 'Add spreadsheet'}
                        </button>
                        {!spreadsheet && (
                            <Link href={index.url()} className="button is-text">
                                Cancel
                            </Link>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}
