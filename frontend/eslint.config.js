import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import ts from 'typescript-eslint'
import unusedImports from 'eslint-plugin-unused-imports'
import globals from 'globals'

export default ts.config(
  { ignores: ['dist/**', 'coverage/**', 'src/components/ui/**'] },

  js.configs.recommended,
  ...ts.configs.recommended,
  ...pluginVue.configs['flat/essential'],

  {
    files: ['**/*.{ts,vue}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: globals.browser,
      parserOptions: {
        parser: ts.parser,
      },
    },
    plugins: {
      'unused-imports': unusedImports,
    },
    rules: {
      // Unused imports: auto-fixable
      'no-unused-vars': 'off',
      '@typescript-eslint/no-unused-vars': 'off',
      'unused-imports/no-unused-imports': 'error',
      'unused-imports/no-unused-vars': [
        'warn',
        {
          vars: 'all',
          varsIgnorePattern: '^_',
          args: 'after-used',
          argsIgnorePattern: '^_',
        },
      ],

      // Relax rules that don't apply or conflict with project patterns
      'vue/multi-word-component-names': 'off',
      'vue/require-toggle-inside-transition': 'warn',
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-require-imports': 'warn',
      'no-undef': 'off', // TypeScript handles this via type-checking
    },
  },
)
