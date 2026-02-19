module.exports = {
    content: [
        "./public/**/*.{php,html}",
        "./admin/**/*.php",
        "./staff/**/*.php",
        "./customer/**/*.php",
        "./includes/**/*.php"
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#4F46E5',
                    50: '#EDEEFE',
                    100: '#DDD9FD',
                    200: '#BBB3FB',
                    300: '#9A8EF9',
                    400: '#7868F7',
                    500: '#4F46E5',
                    600: '#3F38B7',
                    700: '#2F2A89',
                    800: '#201C5B',
                    900: '#100E2E',
                },
                secondary: {
                    DEFAULT: '#10B981',
                    50: '#E3FCF1',
                    100: '#C6F9E4',
                    200: '#8DF3C9',
                    300: '#54EDAE',
                    400: '#1BE793',
                    500: '#10B981',
                    600: '#0D9467',
                    700: '#0A6F4D',
                    800: '#064A33',
                    900: '#032519',
                },
                accent: {
                    purple: '#A855F7',
                    pink: '#EC4899',
                    cyan: '#06B6D4',
                    orange: '#F97316',
                }
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
            },
            animation: {
                'fade-in': 'fadeIn 0.5s ease-in-out',
                'fade-in-up': 'fadeInUp 0.6s ease-out',
                'slide-up': 'slideUp 0.4s ease-out',
                'pulse-subtle': 'pulseSubtle 2s ease-in-out infinite',
                'bounce-subtle': 'bounceSubtle 1s ease-in-out infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                fadeInUp: {
                    '0%': { opacity: '0', transform: 'translateY(20px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideUp: {
                    '0%': { transform: 'translateY(10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                pulseSubtle: {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '0.8' },
                },
                bounceSubtle: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-5px)' },
                },
            },
            backdropBlur: {
                xs: '2px',
            },
            boxShadow: {
                'glow': '0 0 20px rgba(79, 70, 229, 0.3)',
                'glow-sm': '0 0 10px rgba(79, 70, 229, 0.2)',
                'card': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                'card-hover': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
            },
        },
    },
    plugins: [],
};
