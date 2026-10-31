export default function ApiKeyHint({ hasApiKey }: { hasApiKey: boolean }) {
    if (hasApiKey) {
        return (
            <div className="notification is-info is-light">
                Google API key is set. It reads only spreadsheets shared as{' '}
                <strong>Anyone with the link → Viewer</strong>.
            </div>
        );
    }

    return (
        <div className="notification is-warning is-light">
            Google API key is not configured. Set <code>GOOGLE_API_KEY</code> in{' '}
            <code>.env</code> (see README).
        </div>
    );
}
