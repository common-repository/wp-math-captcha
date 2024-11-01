var initMathCaptcha = function () {
	MathCaptcha = {
		promise: null,
		args: {},

		/**
		 * Initialize counter.
		 *
		 * @param {object} args
		 *
		 * @return {void}
		 */
		init: function ( args ) {
			this.args = args;

			console.log( args );

			// find mcaptcha fields
			this.find();
		},

		/**
		 * Generate mCaptcha fields.
		 *
		 * @return {void}
		 */
		template: function () {
			// create a new div element
			var wrapper = document.createElement( 'div' );
			wrapper.classList.add( 'mcaptcha' );
			wrapper.classList.add( 'theme-' + this.args.theme );
			var container = document.createElement( 'div' );
			container.classList.add( 'mcaptcha-container' );
			var title = document.createElement( 'span' );
			title.classList.add( 'mcaptcha-title' );
			title.innerHTML = this.args.title;
			var repeat = document.createElement( 'span' );
			repeat.classList.add( 'mcaptcha-repeat' );
			var phrase = document.createElement( 'span' );
			phrase.classList.add( 'mcaptcha-phrase' );
			var loader = document.createElement( 'span' );
			loader.classList.add( 'mcaptcha-loader' );

			container.appendChild( title );
			container.appendChild( phrase );
			container.appendChild( loader );
			
			if ( this.args.reloading === true ) {
				container.appendChild( repeat );
			}
			
			wrapper.appendChild( container );

			return wrapper;
		},

		/**
		 * Find mCaptcha fields.
		 *
		 * @return {void}
		 */
		find: function () {
			const _this = this;
			
			var fields = document.getElementsByClassName( 'mcaptcha-placeholder' )
			var template = this.template();

			console.log( fields );
			// console.log( template );

			if ( fields.length > 0 ) {
				for ( var step = 0; step < fields.length; step ++ ) {
					// get the parent element
					let parent = fields[step].parentNode;
					let field = fields[step];

					// insert template
					parent.insertBefore( template, fields[step] );

					// get form id from field
					let form_id = field.getAttribute( 'data-mcaptchaid' );

					// remove placeholder
					// field.remove();

					// find container
					let container = parent.querySelector( '.mcaptcha' );
					container.classList.add( form_id );

					// find repeat button
					let repeat = parent.querySelector( '.mcaptcha-repeat' );
					
					// default parameters
					let params = {};

					// set params
					params.action = 'mcaptcha_get';
					params.nonce = this.args.nonce;
					params.form_id = form_id;

					// add repeat event listener, if enabled
					if ( this.args.reloading === true ) {
						repeat.addEventListener( 'click', function ( e ) {
							// make request
							_this.promise = _this.request( _this.args.requestURL, params, 'POST', {
								'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
							}, container );
						}, false );
					}

					// make request
					this.promise = this.request( this.args.requestURL, params, 'POST', {
						'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
					}, container );
				}
			}
		},

		/**
		 * Handle fetch request.
		 *
		 * @param {string} url
		 * @param {object} params
		 * @param {string} method
		 * @param {object} headers
		 *
		 * @return {object}
		 */
		request: function ( url, params, method, headers, container ) {
			let options = {
				method: method,
				mode: 'cors',
				cache: 'no-cache',
				credentials: 'same-origin',
				headers: headers,
				body: this.prepareRequestData( params )
			};

			const _this = this;

			// add loading class to container, if exist
			if ( typeof container !== 'undefined' && container instanceof Node ) {
				container.classList.add( 'loading' );
			}

			return fetch( url, options ).then( function ( response ) {
				// invalid response?
				if ( ! response.ok ) {
					throw Error( response.statusText );

					// add error class to container, if exist
					if ( typeof container !== 'undefined' && container instanceof Node ) {
						container.classList.add( 'error' );
					}
				}

				return response.json();
			} ).then( function ( response ) {
				try {
					if ( typeof response === 'object' && response !== null ) {
						var phrase = container.querySelector( '.mcaptcha-phrase' );

						phrase.innerHTML = response.form;
					}
				} catch ( error ) {
					// add error class to container, if exist
					if ( typeof container !== 'undefined' && container instanceof Node ) {
						container.classList.add( 'error' );
					}

					console.log( 'Invalid JSON data' );
					console.log( error );
				}
			} ).catch( function ( error ) {
				// add error class to container, if exist
				if ( typeof container !== 'undefined' && container instanceof Node ) {
					container.classList.add( 'error' );
				}

				console.log( 'Invalid response' );
				console.log( error );
			} ).finally( function () {
				// remove loading class to container, if exist
				if ( typeof container !== 'undefined' && container instanceof Node ) {
					container.classList.remove( 'loading' );
				}
			} );
		},

		/**
		 * Prepare the data to be sent with the request.
		 *
		 * @param {object} data
		 *
		 * @return {string}
		 */
		prepareRequestData: function ( data ) {
			return Object.keys( data ).map( function ( el ) {
				// add extra "data" array
				return encodeURIComponent( el ) + '=' + encodeURIComponent( data[el] );
			} ).join( '&' ).replace( /%20/g, '+' );
		},
	}

	if ( typeof mCaptchaArgs !== 'undefined' )
		MathCaptcha.init( mCaptchaArgs );
}

document.addEventListener( 'DOMContentLoaded', initMathCaptcha );