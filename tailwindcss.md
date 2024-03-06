# Tailwind course v3.4.1

## Table of contents

- [Tailwind course v3.4.1](#tailwind-course-v341)
  - [Table of contents](#table-of-contents)
  - [Colors](#colors)
    - [Customize color](#customize-color)
  - [Customize (configuration)](#customize-configuration)
  - [Plugin](#plugin)
  - [Typography](#typography)
  - [Spacing](#spacing)
  - [Flex](#flex)
  - [Grid](#grid)
  - [layouts](#layouts)
  - [Borders](#borders)
  - [Effects \& Filters](#effects--filters)
  - [Animations](#animations)
  - [Design system](#design-system)
  - [Core concept](#core-concept)
  - [Dark mode](#dark-mode)

## Colors

[Default palette](https://tailwindcss.com/docs/customizing-colors)

Add the color class like :

```html
<div classCustomize color="bg-green-200 text-white border-2 border-red-50">
    <span>Some text</span>
    <p class="text-yellow-600">Another text</p>
</div>
```

### Customize color

[Customize color part](https://youtu.be/ft30zcMlFao)

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.js",
    "./templates/**/*.html.twig",
  ],
  theme: {
    extend: {
        color: {
            myCustomColor: "#49e659"
        },
    },
  },
  plugins: [],
}
```

```html
<p class="text-myCustomColor">Another text</p>
```

## Customize (configuration)

[Part](https://youtu.be/ft30zcMlFao?t=3928)

Example :

```js
/** @type {import('tailwindcss').Config} */
module.exports = {

  content: ['./src/**/*.{html,js}'],
  theme: {

    screens: {

      'sm': '640px',
      // => @media (min-width: 640px) { ... }

      'md': '768px',
      // => @media (min-width: 768px) { ... }

      'lg': '1024px',
      // => @media (min-width: 1024px) { ... }

      'xl': '1280px',
      // => @media (min-width: 1280px) { ... }

      '2xl': '1536px',
      // => @media (min-width: 1536px) { ... }
    },
    colors: {

      'blue': '#1fb6ff',
      'purple': '#7e5bef',
      'pink': '#ff49db',
      'orange': '#ff7849',
      'green': '#13ce66',
      'yellow': '#ffc82c',
      'gray-dark': '#273444',
      'gray': '#8492a6',
      'gray-light': '#d3dce6',
    },
    fontFamily: {

      sans: ['Graphik', 'sans-serif'],
      serif: ['Merriweather', 'serif'],
    },
    extend: {

      spacing: {

        '8xl': '96rem',
        '9xl': '128rem',
      },
      borderRadius: {

        '4xl': '2rem',
      }
    }
  },
}
```

## Plugin

 Apply code all over the page.

```js
@layer base {
  html {

    @apply: bg-slate-600;
    @apply: text-white;
  }
}
```

## Typography

- [font size text-sm](https://youtu.be/ft30zcMlFao?t=2680)
- [configure fontsize](https://youtu.be/ft30zcMlFao)
- [import font](https://youtu.be/ft30zcMlFao?t=3023)

## Spacing

-[spacing section](https://youtu.be/ft30zcMlFao?t=4251)


## Flex

## Grid

## layouts

## Borders

## Effects & Filters

## Animations

## Design system

## Core concept

## Dark mode
