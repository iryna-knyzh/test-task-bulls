export type ColumnType =
    | 'string'
    | 'text'
    | 'integer'
    | 'decimal'
    | 'date'
    | 'datetime'
    | 'boolean';

export type SyncStatus = 'running' | 'success' | 'failed';

export type ColumnMapping = {
    id: number;
    source_column: string;
    target_column: string;
    data_type: ColumnType;
};

export type TabMapping = {
    id: number;
    sheet_tab_id: number;
    target_table: string;
    columns?: ColumnMapping[];
};

export type SheetTab = {
    id: number;
    spreadsheet_id: number;
    tab_name: string;
    header_row: number;
    mappings?: TabMapping[];
    spreadsheet?: Spreadsheet;
};

export type SyncRun = {
    id: number;
    spreadsheet_id: number;
    status: SyncStatus;
    rows_processed: number;
    warnings: string[] | null;
    error: string | null;
    started_at: string;
    finished_at: string | null;
    spreadsheet?: Pick<Spreadsheet, 'id' | 'name'>;
};

export type Spreadsheet = {
    id: number;
    name: string;
    google_spreadsheet_id: string;
    is_active: boolean;
    last_synced_at: string | null;
    tabs_count?: number;
    tabs?: SheetTab[];
    latest_sync_run?: SyncRun | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
