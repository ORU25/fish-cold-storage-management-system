import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, LogIn } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Turnstile } from '@/components/turnstile';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

type LoginForm = {
    username: string;
    password: string;
    remember: boolean;
    turnstile_token: string;
};

interface LoginProps {
    status?: string;
    /** `required` is false in development: the demo widget always passes and the server does not check it. */
    turnstile: { siteKey: string; required: boolean };
}

export default function Login({ status, turnstile }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        username: '',
        password: '',
        remember: false,
        turnstile_token: '',
    });
    const [showPassword, setShowPassword] = useState(false);
    // Bumped after every submit, because a Turnstile token can only be used once.
    const [turnstileKey, setTurnstileKey] = useState(0);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => {
                reset('password', 'turnstile_token');
                setTurnstileKey((key) => key + 1);
            },
        });
    };

    return (
        <AuthLayout title="Masuk ke akun Anda" description="Masukkan username dan password">
            <Head title="Masuk" />

            {status && <div className="text-center text-sm font-medium text-green-600">{status}</div>}

            <form className="grid gap-5" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="username">Username</Label>
                    <Input
                        id="username"
                        type="text"
                        required
                        autoFocus
                        autoComplete="username"
                        autoCapitalize="none"
                        className="h-11"
                        value={data.username}
                        onChange={(e) => setData('username', e.target.value)}
                    />
                    <InputError message={errors.username} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <div className="relative">
                        <Input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            required
                            autoComplete="current-password"
                            className="h-11 pr-11"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((shown) => !shown)}
                            className="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex w-11 items-center justify-center"
                            aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                        >
                            {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                        </button>
                    </div>
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox id="remember" checked={data.remember} onCheckedChange={(checked) => setData('remember', checked === true)} />
                    <Label htmlFor="remember">Ingat saya</Label>
                </div>

                <div className="grid gap-2">
                    <Turnstile key={turnstileKey} siteKey={turnstile.siteKey} onToken={(token) => setData('turnstile_token', token)} />
                    <InputError message={errors.turnstile_token} />
                </div>

                <Button type="submit" size="lg" className="w-full" disabled={processing || (turnstile.required && !data.turnstile_token)}>
                    {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <LogIn />}
                    Masuk
                </Button>
            </form>
        </AuthLayout>
    );
}
