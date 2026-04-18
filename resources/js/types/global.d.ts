import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            notificationUnreadCount: number;
            flash: {
                success?: string | null;
                error?: string | null;
                undo?: {
                    method: string;
                    url: string;
                    label: string;
                } | null;
            };
            [key: string]: unknown;
        };
    }
}
