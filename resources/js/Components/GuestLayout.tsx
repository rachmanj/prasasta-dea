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
                    <div
                        style={{
                            display: 'inline-block',
                            background: '#ffffff',
                            borderRadius: 12,
                            padding: '12px 20px',
                            marginBottom: 14,
                            boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
                        }}
                    >
                        <img
                            src="/images/logo-prasasta.png"
                            alt="Prasasta Learning Centre"
                            style={{ width: 190, height: 'auto', display: 'block' }}
                        />
                    </div>
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
