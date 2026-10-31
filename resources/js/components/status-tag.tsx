import type { SyncStatus } from '@/types';

const colorByStatus: Record<SyncStatus, string> = {
    running: 'is-info',
    success: 'is-success',
    failed: 'is-danger',
};

export default function StatusTag({
    status,
}: {
    status: SyncStatus | null | undefined;
}) {
    if (!status) {
        return <span className="tag is-light">never synced</span>;
    }

    return <span className={`tag ${colorByStatus[status]}`}>{status}</span>;
}
