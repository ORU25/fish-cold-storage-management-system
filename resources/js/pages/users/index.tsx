import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Role, type User } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, Save } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type UserRow = Pick<User, 'id' | 'name' | 'username' | 'role' | 'is_active'>;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pengguna', href: '/users' }];

export default function UsersIndex({ users, roles }: { users: UserRow[]; roles: Role[] }) {
    const [editing, setEditing] = useState<UserRow | null>(null);
    const [open, setOpen] = useState(false);
    const emptyForm = { name: '', username: '', role: 'staff' as Role, password: '', is_active: true as boolean };
    const form = useForm(emptyForm);

    const openForm = (user: UserRow | null) => {
        form.clearErrors();
        form.setData(user ? { name: user.name, username: user.username, role: user.role, password: '', is_active: user.is_active } : emptyForm);
        setEditing(user);
        setOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) {
            form.put(route('users.update', editing.id), options);
        } else {
            form.post(route('users.store'), options);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengguna" />
            <div className="p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title="Pengguna" description="Kelola akun Staff, Admin, dan Owner. Akun tidak bisa dihapus, hanya dinonaktifkan." />
                    <Button onClick={() => openForm(null)}>
                        <Plus /> Tambah
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Nama</th>
                                <th className="p-3">Username</th>
                                <th className="p-3">Role</th>
                                <th className="p-3">Status</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="p-3">{user.name}</td>
                                    <td className="p-3">{user.username}</td>
                                    <td className="p-3 capitalize">{user.role}</td>
                                    <td className="p-3">
                                        <Badge variant={user.is_active ? 'success' : 'neutral'}>{user.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                                    </td>
                                    <td className="p-3 text-right">
                                        <Button variant="outline" size="sm" onClick={() => openForm(user)}>
                                            <Pencil /> Ubah
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editing ? `Ubah ${editing.username}` : 'Tambah pengguna'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nama</Label>
                            <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            <InputError message={form.errors.name} />
                        </div>
                        {!editing && (
                            <div className="grid gap-2">
                                <Label htmlFor="username">Username</Label>
                                <Input
                                    id="username"
                                    autoCapitalize="none"
                                    value={form.data.username}
                                    onChange={(e) => form.setData('username', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.username} />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="role">Role</Label>
                            <select
                                id="role"
                                className="border-input bg-background h-10 rounded-md border pl-3 pr-10 text-sm capitalize"
                                value={form.data.role}
                                onChange={(e) => form.setData('role', e.target.value as Role)}
                            >
                                {roles.map((role) => (
                                    <option key={role} value={role}>
                                        {role}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.role} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">{editing ? 'Reset password (kosongkan jika tidak diubah)' : 'Password'}</Label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                required={!editing}
                            />
                            <InputError message={form.errors.password} />
                        </div>
                        {editing && (
                            <div className="grid gap-2">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_active}
                                        onChange={(e) => form.setData('is_active', e.target.checked)}
                                    />
                                    Aktif (bisa login)
                                </label>
                                <InputError message={form.errors.is_active} />
                            </div>
                        )}
                        <DialogFooter>
                            <Button type="submit" disabled={form.processing}>
                                <Save /> Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
