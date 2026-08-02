import { defineConfig } from 'vitest/config'

export default defineConfig({
	test: {
		environment: 'jsdom',
		include: ['tests/js/**/*.spec.js'],
		setupFiles: ['tests/js/setup.js'],
	},
})
