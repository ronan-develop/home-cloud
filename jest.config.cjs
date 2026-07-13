module.exports = {
  testEnvironment: './jest.environment.cjs',
  testTimeout: 10000,
  transform: {},
  moduleNameMapper: {
    '^@hotwired/stimulus$': '<rootDir>/assets/vendor/@hotwired/stimulus/stimulus.index.js',
  },
};
