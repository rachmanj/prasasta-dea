import { FlashMessages } from '@/Components/FlashMessages';
import { useTheme } from '@/theme';
import {
    BankOutlined,
    BarChartOutlined,
    DashboardOutlined,
    FileTextOutlined,
    LogoutOutlined,
    MoonOutlined,
    SettingOutlined,
    SunOutlined,
    SwapOutlined,
    UserOutlined,
} from '@ant-design/icons';
import { router, usePage } from '@inertiajs/react';
import {
    Avatar,
    Button,
    Dropdown,
    Layout,
    Menu,
    Space,
} from 'antd';
import type { MenuProps } from 'antd';
import { useMemo } from 'react';
import type { ReactNode } from 'react';

const { Header, Sider, Content } = Layout;

export default function AppLayout({ children }: { children: ReactNode }) {
    const { mode, toggle } = useTheme();
    const { auth } = usePage().props;
    const user = auth.user;
    const isAdmin = user?.roles?.includes('admin') ?? false;
    const canManage = isAdmin || (user?.roles?.includes('bendahara') ?? false);
    const path = usePage().url;

    const menuItems: MenuProps['items'] = [
        { key: '/dashboard', icon: <DashboardOutlined />, label: 'Dashboard' },
        ...(canManage
            ? [
                  {
                      key: 'trans',
                      icon: <SwapOutlined />,
                      label: 'Transaksi',
                      children: [
                          { key: '/transactions?type=receipt', label: 'Uang Masuk' },
                          { key: '/transactions?type=payment', label: 'Uang Keluar' },
                          { key: '/transactions?type=transfer', label: 'Transfer' },
                          { key: '/transactions?type=journal', label: 'Jurnal Umum' },
                      ],
                  },
                  {
                      key: 'bills',
                      icon: <FileTextOutlined />,
                      label: 'Hutang & Piutang',
                      children: [
                          { key: '/bills?type=receivable', label: 'Piutang' },
                          { key: '/bills?type=payable', label: 'Hutang' },
                      ],
                  },
                  {
                      key: '/reconciliations',
                      icon: <BankOutlined />,
                      label: 'Rekonsiliasi Bank',
                  },
              ]
            : []),
        {
            key: 'reports',
            icon: <BarChartOutlined />,
            label: 'Laporan',
            children: [
                { key: '/reports/cash-flow', label: 'Arus Kas' },
                { key: '/reports/profit-loss', label: 'Laba Rugi' },
                {
                    key: '/reports/receivables-payables',
                    label: 'Hutang & Piutang',
                },
            ],
        },
        ...(canManage
            ? [
                  {
                      key: 'master',
                      icon: <SettingOutlined />,
                      label: 'Master',
                      children: [
                          { key: '/accounts', label: 'Chart of Accounts' },
                          { key: '/contacts', label: 'Kontak' },
                          ...(isAdmin ? [{ key: '/opening-balances', label: 'Saldo Awal' }] : []),
                          ...(isAdmin ? [{ key: '/users', label: 'Users' }] : []),
                      ],
                  },
              ]
            : []),
    ];

    const selectedKey = useMemo(() => {
        if (path.startsWith('/transactions')) {
            const u = new URL(path, window.location.origin);
            return '/transactions?type=' + (u.searchParams.get('type') || 'receipt');
        }
        if (path.startsWith('/bills')) {
            const u = new URL(path, window.location.origin);
            return '/bills?type=' + (u.searchParams.get('type') || 'receivable');
        }
        if (path.startsWith('/accounts')) return '/accounts';
        if (path.startsWith('/opening-balances')) return '/opening-balances';
        if (path.startsWith('/contacts')) return '/contacts';
        if (path.startsWith('/users')) return '/users';
        if (path.startsWith('/reconciliations')) return '/reconciliations';
        if (path.startsWith('/reports/cash-flow')) return '/reports/cash-flow';
        if (path.startsWith('/reports/profit-loss')) return '/reports/profit-loss';
        if (path.startsWith('/reports/receivables-payables'))
            return '/reports/receivables-payables';
        return '/dashboard';
    }, [path]);

    const onClick: MenuProps['onClick'] = ({ key }) => {
        if (key.startsWith('/')) router.get(key);
    };

    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider breakpoint="lg" collapsedWidth="0">
                <div
                    style={{
                        height: 48,
                        margin: 16,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: '#fff',
                        fontWeight: 700,
                        fontSize: 16,
                    }}
                >
                    Prasasta ERP
                </div>
                <Menu
                    theme="dark"
                    mode="inline"
                    selectedKeys={[selectedKey]}
                    defaultOpenKeys={['trans', 'bills', 'reports', 'master']}
                    items={menuItems}
                    onClick={onClick}
                />
            </Sider>

            <Layout>
                <Header
                    style={{
                        padding: '0 24px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'flex-end',
                    }}
                >
                    <Space size="middle">
                        <Button
                            type="text"
                            icon={
                                mode === 'dark' ? <SunOutlined /> : <MoonOutlined />
                            }
                            onClick={toggle}
                        />
                        <Dropdown
                            menu={{
                                items: [
                                    {
                                        key: 'logout',
                                        icon: <LogoutOutlined />,
                                        label: 'Keluar',
                                    },
                                ],
                                onClick: ({ key }) => {
                                    if (key === 'logout') router.post(route('logout'));
                                },
                            }}
                        >
                            <Space style={{ cursor: 'pointer' }}>
                                <Avatar size="small" icon={<UserOutlined />} />
                                <span>{user?.name}</span>
                            </Space>
                        </Dropdown>
                    </Space>
                </Header>

                <Content style={{ margin: 24 }}>{children}</Content>
            </Layout>

            <FlashMessages />
        </Layout>
    );
}
