export type User = {
    id: number;
    name: string;
    email: string;
    country_code: string | null;
    email_verified_at: string | null;
};

export type Auth = {
    user: User | null;
};
