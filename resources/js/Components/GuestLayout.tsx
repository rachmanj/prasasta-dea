import { FlashMessages } from '@/Components/FlashMessages';
import { useTheme } from '@/theme';
import { MoonOutlined, SunOutlined } from '@ant-design/icons';
import { Button, Card, Layout, Typography } from 'antd';
import type { ReactNode } from 'react';

export default function GuestLayout({ children }: { children: ReactNode }) {
    const { mode, toggle } = useTheme();

    return (
        <Layout
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
            }}
        >
            <div style={{ width: '100%', maxWidth: 420, padding: 24 }}>
                <div style={{ textAlign: 'center', marginBottom: 20 }}>
                    <Typography.Title level={3} style={{ marginBottom: 0 }}>
                        Prasasta ERP
                    </Typography.Title>
                    <Typography.Text type="secondary">
                        Keuangan Yayasan LPK
                    </Typography.Text>
                </div>

                <Card>{children}</Card>

                <div style={{ textAlign: 'center', marginTop: 12 }}>
                    <Button
                        type="text"
                        icon={mode === 'dark' ? <SunOutlined /> : <MoonOutlined />}
                        onClick={toggle}
                    >
                        {mode === 'dark' ? 'Mode Terang' : 'Mode Gelap'}
                    </Button>
                </div>
            </div>

            <FlashMessages />
        </Layout>
    );
}
