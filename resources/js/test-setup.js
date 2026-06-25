import { vi } from 'vitest';

vi.mock('driver.js', () => ({
    driver: vi.fn(() => ({
        drive: vi.fn(),
        destroy: vi.fn(),
    })),
}));
