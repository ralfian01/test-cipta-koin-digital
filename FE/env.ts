// // This module sets up a global 'process' object to mimic Node.js environment variables,
// // specifically for environments where `process.env` is not available.

// // Ensure the global 'process' and 'process.env' objects exist.
// // We attach it to `window` to make it globally accessible in a browser context.
// if (typeof (window as any).process === 'undefined') {
//   (window as any).process = {};
// }
// if (typeof (window as any).process.env === 'undefined') {
//   (window as any).process.env = {};
// }

// // Set the API URL on the global object.
// (window as any).process.env.API_URL = 'https://api.majukoperasiku.my.id';

// // Exporting something is good practice for modules with side effects,
// // even if it's not directly used everywhere. This also tells TypeScript
// // that this is a module.
// export { };
