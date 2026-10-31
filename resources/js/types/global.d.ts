export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
        };
    }
}
